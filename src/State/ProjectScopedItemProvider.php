<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ProjectScopedResource;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class ProjectScopedItemProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $id = (string) ($uriVariables['id'] ?? '');
        $project = $this->projectAccessGuard->getReadableProject($projectId);
        $class = $operation->getClass();

        if (is_a($class, ProjectScopedResource::class, true)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException('Resource not found.');
            }

            $resource = $this->entityManager->getRepository($class)->findOneBy([
                'id' => Uuid::fromString($id),
                'project' => $project,
                'deletedAt' => null,
            ]);

            if (!$resource instanceof ProjectScopedResource) {
                throw new NotFoundHttpException('Resource not found.');
            }

            return $resource;
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
