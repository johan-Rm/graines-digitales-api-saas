<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProjectScopedResource;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectScopedRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ProjectScopedResource) {
            $projectId = (string) ($uriVariables['projectId'] ?? '');
            $this->projectAccessGuard->getWritableProject($projectId);
            $data->archive($this->currentUserProvider->getUser());
            $this->entityManager->flush();

            return $data;
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
