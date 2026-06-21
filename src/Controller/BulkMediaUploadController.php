<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MediaObject;
use App\Entity\Project;
use App\Media\MediaCaptionGuesser;
use App\Media\MediaContentUrlResolver;
use App\Message\AnalyzeMediaObject;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
final readonly class BulkMediaUploadController
{
    private const MAX_FILES = 20;
    private const SUPPORTED_LOCALES = ['fr', 'en', 'es', 'ar'];

    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
        private MediaCaptionGuesser $mediaCaptionGuesser,
        private MediaContentUrlResolver $mediaContentUrlResolver,
    ) {
    }

    public function __invoke(Request $request, string $projectId): JsonResponse
    {
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();
        $locale = $this->resolveLocale($request, $project);
        $files = $this->extractFiles($request);

        if ([] === $files) {
            return new JsonResponse([
                'message' => 'At least one image file is required.',
                'created' => [],
                'errors' => [],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (count($files) > self::MAX_FILES) {
            return new JsonResponse([
                'message' => sprintf('A bulk upload accepts at most %d files.', self::MAX_FILES),
                'created' => [],
                'errors' => [],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $created = [];
        $errors = [];
        $captionOverride = $this->optionalString($request, 'caption');

        foreach ($files as $index => $file) {
            $caption = $captionOverride ?? $this->mediaCaptionGuesser->guessFromFilename($file->getClientOriginalName());

            $mediaObject = new MediaObject();
            $mediaObject->setProject($project);
            $mediaObject->setResolvedLocale($locale, $locale, null);
            $mediaObject->markCreatedBy($actor);
            $mediaObject->touch($actor);
            $mediaObject->setFile($file);
            $mediaObject->setCaption($caption);
            $checksum = hash_file('sha256', $file->getPathname());
            $mediaObject->setChecksumSha256(false === $checksum ? null : $checksum);

            $violations = $this->validator->validate($mediaObject, null, ['Default', 'media:create']);

            if (count($violations) > 0) {
                $errors[] = [
                    'index' => $index,
                    'originalFilename' => $file->getClientOriginalName(),
                    'violations' => array_map(
                        static fn ($violation): array => [
                            'propertyPath' => $violation->getPropertyPath(),
                            'message' => $violation->getMessage(),
                        ],
                        iterator_to_array($violations),
                    ),
                ];

                continue;
            }

            $this->entityManager->persist($mediaObject);
            $this->entityManager->flush();
            $this->messageBus->dispatch(new AnalyzeMediaObject((string) $mediaObject->getId()));

            $created[] = [
                'index' => $index,
                'id' => (string) $mediaObject->getId(),
                'contentUrl' => $this->mediaContentUrlResolver->resolve($mediaObject),
                'filePath' => $mediaObject->getFilePath(),
                'mimeType' => $mediaObject->getMimeType(),
                'size' => $mediaObject->getSize(),
                'originalFilename' => $mediaObject->getOriginalFilename(),
                'checksumSha256' => $mediaObject->getChecksumSha256(),
                'caption' => $mediaObject->getCaption(),
            ];
        }

        if ([] === $created) {
            return new JsonResponse([
                'message' => 'No image file could be uploaded.',
                'created' => [],
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'created' => $created,
            'errors' => $errors,
        ], [] === $errors ? Response::HTTP_CREATED : Response::HTTP_MULTI_STATUS);
    }

    /**
     * @return list<UploadedFile>
     */
    private function extractFiles(Request $request): array
    {
        $files = $request->files->all('files');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter(
                $files,
                static fn (mixed $file): bool => $file instanceof UploadedFile,
            ));
        }

        return [];
    }

    private function optionalString(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);

        return is_string($value) && '' !== trim($value) ? $value : null;
    }

    private function resolveLocale(Request $request, Project $project): string
    {
        $queryLocale = $request->query->get('locale');
        $locale = $queryLocale;
        if (!is_string($locale) || '' === $locale) {
            $locale = $request->headers->get('Accept-Language');
        }
        $locale = is_string($locale) && '' !== $locale ? substr($locale, 0, 2) : $project->getSourceLocale();

        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new BadRequestHttpException('Unsupported locale.');
        }

        if (!in_array($locale, $project->getEnabledLocales(), true)) {
            throw new BadRequestHttpException('Locale is not enabled for this project.');
        }

        return $locale;
    }
}
