<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AccommodationTranslation;
use App\Entity\ProjectScopedResource;
use App\Entity\TranslatableResource;
use App\Entity\User;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ProjectScopedBulkTranslationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private DenormalizerInterface $denormalizer,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof BulkTranslationInput) {
            return null;
        }

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

        if (!Uuid::isValid($data->resourceId)) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $resource = $this->entityManager->getRepository($resourceClass)->findOneBy([
            'id' => Uuid::fromString($data->resourceId),
            'project' => $project,
        ]);

        if (!$resource instanceof TranslatableResource || !$resource instanceof ProjectScopedResource) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $this->applyUserTranslations($resource, $data, $actor);
        $this->autoTranslator->synchronize($resource, $project);
        $this->finalizeTranslations($resource, $actor);

        $resource->touch($actor);
        $this->entityManager->persist($resource);
        $this->entityManager->flush();

        return $resource;
    }

    private function applyUserTranslations(TranslatableResource $resource, BulkTranslationInput $data, ?User $actor): void
    {
        foreach ($data->translations as $translationData) {
            $locale = is_array($translationData) ? ($translationData['locale'] ?? '') : '';
            if (!is_string($locale) || '' === $locale) {
                continue;
            }

            $translation = $resource->getOrCreateTranslation($locale);

            $this->denormalizer->denormalize($translationData, $translation::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $translation,
                AbstractNormalizer::GROUPS => ['write'],
            ]);

            $translation->setTranslatable($resource);
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }
    }

    private function finalizeTranslations(TranslatableResource $resource, ?User $actor): void
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
}
