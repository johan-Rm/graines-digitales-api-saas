<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProjectScopedResource;
use App\Security\ProjectAccessGuard;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ProjectScopedRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ProjectScopedResource) {
            $this->projectAccessGuard->getWritableProject((string) ($uriVariables['projectId'] ?? ''));
        }

        try {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        } catch (ForeignKeyConstraintViolationException $e) {
            throw new ConflictHttpException('This resource is still referenced by other records and cannot be deleted.', $e);
        }
    }
}
