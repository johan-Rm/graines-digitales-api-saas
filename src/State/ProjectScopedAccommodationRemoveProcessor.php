<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectScopedAccommodationRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Accommodation) {
            return null;
        }

        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $this->projectAccessGuard->getWritableProject($projectId);

        // Clear ManyToMany join tables before removal to avoid FK constraint issues
        $data->clearAmenityFeature();
        $data->clearTags();

        // Dissociate media (AccommodationMedia rows deleted, MediaObject entities kept)
        $data->clearAssociatedMedia();

        // Persist the cleared state so Doctrine tracks the changes before deletion
        $this->entityManager->persist($data);

        // Hard-delete — cascades on translations, qualities via ORM cascade: ['remove']
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
