<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OrganizationMember;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CreateProjectMemberUserController
{
    private const ALLOWED_USER_ROLES = ['ROLE_USER', 'ROLE_ADMIN'];

    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/projects/{projectId}/members', name: 'api_project_members_create_user', methods: ['POST'])]
    public function __invoke(Request $request, string $projectId): JsonResponse
    {
        $project = $this->projectAccessGuard->getProjectForMemberManagement($projectId);

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($payload, new Assert\Collection(
            fields: [
                'email' => new Assert\Required([
                    new Assert\NotBlank(),
                    new Assert\Email(),
                    new Assert\Length(max: 180),
                ]),
                'password' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8, max: 4096),
                ]),
                'role' => new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\Choice(choices: array_map(
                        static fn (ProjectRole $role): string => $role->value,
                        ProjectRole::cases(),
                    )),
                ]),
                'projectRole' => new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\Choice(choices: array_map(
                        static fn (ProjectRole $role): string => $role->value,
                        ProjectRole::cases(),
                    )),
                ]),
                'roles' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Type('string'),
                        new Assert\Choice(choices: self::ALLOWED_USER_ROLES),
                    ]),
                    new Assert\Count(min: 1),
                ]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));

        if (count($violations) > 0) {
            return new JsonResponse([
                'message' => 'Invalid project member payload.',
                'violations' => array_map(
                    static fn ($violation): array => [
                        'propertyPath' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage(),
                    ],
                    iterator_to_array($violations),
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower((string) $payload['email']);
        $projectRole = ProjectRole::from((string) ($payload['projectRole'] ?? $payload['role'] ?? ProjectRole::Admin->value));
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            return new JsonResponse([
                'message' => 'User must belong to the project organization before project membership can be created.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organization = $project->getOrganization();
        $organizationMembership = $this->entityManager->getRepository(OrganizationMember::class)->findOneBy([
            'organization' => $organization,
            'user' => $user,
        ]);

        if (!$organizationMembership instanceof OrganizationMember) {
            return new JsonResponse([
                'message' => 'User must belong to the project organization before project membership can be created.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);
        $createdMembership = false;

        if (!$membership instanceof ProjectMember) {
            $membership = new ProjectMember();
            $membership->setProject($project);
            $membership->setUser($user);
            $this->entityManager->persist($membership);
            $createdMembership = true;
        }

        $membership->setRole($projectRole);
        $this->entityManager->flush();

        return new JsonResponse([
            'user' => [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'created' => false,
            ],
            'membership' => [
                'id' => (string) $membership->getId(),
                'projectId' => (string) $project->getId(),
                'role' => $membership->getRole()->value,
                'created' => $createdMembership,
            ],
        ], $createdMembership ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
