<?php

namespace App\Security;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class OrganizationAccessGuard
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
        private PlatformSuperAdminResolver $superAdminResolver,
    ) {
    }

    public function assertCanManageOrganizationMembers(Organization $organization): void
    {
        $user = $this->currentUserProvider->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        if ($this->superAdminResolver->isSuperAdmin($user)) {
            return;
        }

        $membership = $this->getMembership($organization, $user);
        if (!$membership instanceof OrganizationMember || !$membership->getRole()->canManageMembers()) {
            throw new AccessDeniedHttpException('Organization member management access is required.');
        }
    }

    public function assertCanCreateProject(Organization $organization): void
    {
        $user = $this->currentUserProvider->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        if ($this->superAdminResolver->isSuperAdmin($user)) {
            return;
        }

        $membership = $this->getMembership($organization, $user);
        if (!$membership instanceof OrganizationMember || !$membership->getRole()->canCreateProject()) {
            throw new AccessDeniedHttpException('Organization project creation access is required.');
        }
    }

    public function canManageProjectMembers(Organization $organization, User $user): bool
    {
        if ($this->superAdminResolver->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->getMembership($organization, $user);

        return $membership instanceof OrganizationMember && $membership->getRole()->canManageMembers();
    }

    private function getMembership(Organization $organization, User $user): ?OrganizationMember
    {
        $membership = $this->entityManager->getRepository(OrganizationMember::class)->findOneBy([
            'organization' => $organization,
            'user' => $user,
        ]);

        return $membership instanceof OrganizationMember ? $membership : null;
    }
}
