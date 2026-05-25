<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Enum\ProjectRole;
use App\Security\CurrentUserProvider;
use App\Security\OrganizationAccessGuard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
        private OrganizationAccessGuard $organizationAccessGuard,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Project && $operation instanceof Post && null !== $data->getOrganization()) {
            $this->organizationAccessGuard->assertCanCreateProject($data->getOrganization());
        }

        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        if (!$data instanceof Project || !$operation instanceof Post) {
            return $result;
        }

        $user = $this->currentUserProvider->getUser();
        if (null === $user) {
            return $result;
        }

        $membership = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $data,
            'user' => $user,
        ]);

        if (!$membership instanceof ProjectMember) {
            $membership = new ProjectMember();
            $membership->setProject($data);
            $membership->setUser($user);
            $membership->setRole(ProjectRole::Owner);
            $membership->markCreatedBy($user);
            $membership->touch($user);

            $this->entityManager->persist($membership);
            $this->entityManager->flush();
        }

        return $result;
    }
}
