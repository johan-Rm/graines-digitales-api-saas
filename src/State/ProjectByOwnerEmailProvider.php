<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ProjectByOwnerEmailProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $email = strtolower((string) ($uriVariables['email'] ?? ''));

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            throw new NotFoundHttpException('No project found for this owner email.');
        }

        $membership = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'user' => $user,
            'role' => ProjectRole::Owner,
        ]);

        if (!$membership instanceof ProjectMember) {
            throw new NotFoundHttpException('No project found for this owner email.');
        }

        $project = $membership->getProject();
        if (!$project instanceof Project) {
            throw new NotFoundHttpException('No project found for this owner email.');
        }

        return $project;
    }
}
