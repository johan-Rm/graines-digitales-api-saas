<?php

namespace App\Security;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class ProjectAccessGuard
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
        private OrganizationAccessGuard $organizationAccessGuard,
    ) {
    }

    public function getReadableProject(string $projectId): Project
    {
        return $this->getProjectForCurrentUser($projectId, false);
    }

    public function getWritableProject(string $projectId): Project
    {
        return $this->getProjectForCurrentUser($projectId, true);
    }

    public function getProjectForMemberManagement(string $projectId): Project
    {
        $user = $this->currentUserProvider->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        if (!Uuid::isValid($projectId)) {
            throw new NotFoundHttpException('Project not found.');
        }

        $project = $this->entityManager->find(Project::class, Uuid::fromString($projectId));
        if (!$project instanceof Project) {
            throw new NotFoundHttpException('Project not found.');
        }

        $membership = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);

        if ($membership instanceof ProjectMember && in_array($membership->getRole(), [ProjectRole::Owner, ProjectRole::Admin], true)) {
            return $project;
        }

        $organization = $project->getOrganization();
        if (null !== $organization && $this->organizationAccessGuard->canManageProjectMembers($organization, $user)) {
            return $project;
        }

        throw new AccessDeniedHttpException('Project member management access is required.');
    }

    private function getProjectForCurrentUser(string $projectId, bool $write): Project
    {
        $user = $this->currentUserProvider->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        if (!Uuid::isValid($projectId)) {
            throw new NotFoundHttpException('Project not found.');
        }

        $project = $this->entityManager->find(Project::class, Uuid::fromString($projectId));
        if (!$project instanceof Project) {
            throw new NotFoundHttpException('Project not found.');
        }

        $membership = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);

        if (!$membership instanceof ProjectMember) {
            throw new NotFoundHttpException('Project not found.');
        }

        if ($write && !$membership->getRole()->canWrite() && ProjectRole::Owner !== $membership->getRole()) {
            throw new AccessDeniedHttpException('Project write access is required.');
        }

        return $project;
    }
}
