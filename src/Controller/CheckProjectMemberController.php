<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final readonly class CheckProjectMemberController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/projects/{projectId}/members/check', name: 'api_project_members_check', methods: ['GET'])]
    public function __invoke(Request $request, string $projectId): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project instanceof Project) {
            throw new NotFoundHttpException('Project not found.');
        }

        $email = strtolower(trim((string) $request->query->get('email', '')));
        if (!$email) {
            return new JsonResponse(['message' => 'Email parameter is required.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return new JsonResponse(['member' => false], Response::HTTP_NOT_FOUND);
        }

        $membership = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);

        if (!$membership instanceof ProjectMember) {
            return new JsonResponse(['member' => false], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'member' => true,
            'role' => $membership->getRole()->value,
            'user' => ['email' => $user->getEmail()],
        ]);
    }
}
