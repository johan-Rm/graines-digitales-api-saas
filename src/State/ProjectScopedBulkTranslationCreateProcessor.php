<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AccommodationTranslation;
use App\Entity\CategoryCode;
use App\Entity\CategoryCodeTranslation;
use App\Entity\MediaObject;
use App\Entity\ProjectScopedResource;
use App\Entity\TranslatableResource;
use App\Media\MediaCaptionGuesser;
use App\Media\MediaUploadFinalizer;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\LocaleResolver;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Handles POST translations routes in two modes depending on the body:
 *
 * CREATE mode (no "resourceId" in body):
 *   Builds a new entity from body fields (write group), applies translations,
 *   then auto-generates any computed fields (e.g. CategoryCode.code from label).
 *
 * APPLY mode ("resourceId" present):
 *   Finds the existing entity by UUID and project, applies translations to it.
 */
final readonly class ProjectScopedBulkTranslationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private DenormalizerInterface $denormalizer,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
        private RequestStack $requestStack,
        private LocaleResolver $localeResolver,
        private MediaCaptionGuesser $mediaCaptionGuesser,
        private MediaUploadFinalizer $mediaUploadFinalizer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();

        $resourceClass = $operation->getClass();
        if (
            !is_string($resourceClass)
            || !is_a($resourceClass, ProjectScopedResource::class, true)
            || !is_a($resourceClass, TranslatableResource::class, true)
        ) {
            return null;
        }

        $body = $this->parseBody();
        $resourceId = (string) ($body['resourceId'] ?? '');

        if ('' === $resourceId) {
            // CREATE mode: build a new entity from body fields
            $resource = $this->buildFromBody($body, $resourceClass);
            $resource->setProject($project);
            $locale = $this->localeResolver->resolve($project);
            $resource->setResolvedLocale($locale, $locale, null);
            $resource->markCreatedBy($actor);
            $this->prepareMediaObjectUpload($resource);
            $resource->touch($actor);
            $this->entityManager->persist($resource);

            foreach ($this->extractTranslations($body) as $translationData) {
                $this->applyTranslation($resource, $translationData, $actor);
            }

            // Auto-generate computed fields that depend on translations
            if ($resource instanceof CategoryCode) {
                $resource->generateCodeFromLabel($project->getSourceLocale());
                if ('' === $resource->getCodeValue()) {
                    throw new BadRequestHttpException(sprintf(
                        'A translation for the source locale "%s" with a non-empty label is required to generate the code.',
                        $project->getSourceLocale()
                    ));
                }
            }

            $this->autoTranslator->synchronize($resource, $project);
            $this->finalizeTranslations($resource, $actor);
            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                throw new ConflictHttpException('A resource with the same unique identifier already exists.', $e);
            }

            // Same post-upload pipeline as the dedicated media POST routes:
            // copy to originals + watermark (which chains analysis).
            if ($resource instanceof MediaObject) {
                $this->mediaUploadFinalizer->finalize($resource);
            }

            return $resource;
        }

        // APPLY mode: apply translations to an existing entity
        if (!Uuid::isValid($resourceId)) {
            throw new BadRequestHttpException(sprintf('resourceId "%s" is not a valid UUID.', $resourceId));
        }

        $resource = $this->entityManager->getRepository($resourceClass)->findOneBy([
            'id' => Uuid::fromString($resourceId),
            'project' => $project,
        ]);

        if (!$resource instanceof TranslatableResource || !$resource instanceof ProjectScopedResource) {
            throw new NotFoundHttpException('Resource not found.');
        }

        foreach ($this->extractTranslations($body) as $translationData) {
            $this->applyTranslation($resource, $translationData, $actor);
        }

        $this->autoTranslator->synchronize($resource, $project);
        $this->finalizeTranslations($resource, $actor);
        $resource->touch($actor);
        $this->entityManager->persist($resource);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException('A resource with the same unique identifier already exists.', $e);
        }

        return $resource;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function buildFromBody(array $body, string $resourceClass): TranslatableResource&ProjectScopedResource
    {
        $payload = array_diff_key($body, ['translations' => true, 'resourceId' => true]);

        /** @var TranslatableResource&ProjectScopedResource $resource */
        $resource = $this->denormalizer->denormalize($payload, $resourceClass, 'json', [
            AbstractNormalizer::GROUPS => ['write'],
        ]);

        if ($resource instanceof MediaObject) {
            $file = $this->requestStack->getCurrentRequest()?->files->get('file');
            if ($file instanceof UploadedFile) {
                $resource->setFile($file);
            }
        }

        return $resource;
    }

    private function prepareMediaObjectUpload(TranslatableResource&ProjectScopedResource $resource): void
    {
        if (!$resource instanceof MediaObject) {
            return;
        }

        $file = $resource->getFile();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $checksum = hash_file('sha256', $file->getPathname());
        $resource->setChecksumSha256(false === $checksum ? null : $checksum);

        if (null === $resource->getCaption() || '' === trim($resource->getCaption())) {
            $resource->setCaption($this->mediaCaptionGuesser->guessFromFilename($file->getClientOriginalName()));
        }
    }

    private function applyTranslation(TranslatableResource $resource, mixed $translationData, mixed $actor): void
    {
        $locale = is_array($translationData) ? ($translationData['locale'] ?? '') : '';
        if (!is_string($locale) || '' === $locale) {
            return;
        }

        $translation = $resource->getOrCreateTranslation($locale);

        foreach ($translationData as $key => $value) {
            if ('locale' === $key) {
                continue;
            }
            $setter = 'set'.ucfirst((string) $key);
            if (method_exists($translation, $setter)) {
                $translation->{$setter}($value);
            }
        }

        $translation->setTranslatable($resource);
        $translation->touch($actor);
        $this->entityManager->persist($translation);
    }

    private function finalizeTranslations(TranslatableResource $resource, mixed $actor): void
    {
        foreach ($resource->getTranslations() as $translation) {
            $translation->setTranslatable($resource);
            $this->autoFiller->fill($translation);
            if ($translation instanceof AccommodationTranslation) {
                $translation->recomputeSlug();
            }
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array<int, array<string, mixed>>
     */
    private function extractTranslations(array $body): array
    {
        if (!is_array($body['translations'] ?? null)) {
            return [];
        }

        return array_values(array_filter($body['translations'], 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseBody(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'multipart/form-data')) {
            return array_map(
                static function (mixed $value): mixed {
                    if (!is_string($value)) {
                        return $value;
                    }
                    try {
                        return json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        return $value;
                    }
                },
                $request->request->all()
            );
        }

        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            $body = [];
        }

        return $body;
    }
}
