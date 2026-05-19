<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ProjectScopedResource;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectScopedCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getReadableProject($projectId);
        $class = $operation->getClass();

        if (is_a($class, ProjectScopedResource::class, true)) {
            return $this->entityManager->getRepository($class)->findBy([
                'project' => $project,
                'deletedAt' => null,
            ]);
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
