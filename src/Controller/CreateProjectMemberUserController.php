<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CreateProjectMemberUserController
{
    private const ALLOWED_USER_ROLES = ['ROLE_USER', 'ROLE_ADMIN'];

    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/projects/{projectId}/members', name: 'api_project_members_create_user', methods: ['POST'])]
    public function __invoke(Request $request, string $projectId): JsonResponse
    {
        $project = $this->projectAccessGuard->getWritableProject($projectId);

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
        $projectRole = ProjectRole::from((string) ($payload['projectRole'] ?? ProjectRole::Admin->value));
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $createdUser = false;

        if (!$user instanceof User) {
            if (!isset($payload['password']) || !is_string($payload['password'])) {
                return new JsonResponse([
                    'message' => 'Password is required when creating a new user.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = new User();
            $user->setEmail($email);
            $user->setRoles(array_values(array_unique($payload['roles'] ?? ['ROLE_USER'])));
            $user->setPassword($this->passwordHasher->hashPassword($user, $payload['password']));
            $this->entityManager->persist($user);
            $createdUser = true;
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
                'created' => $createdUser,
            ],
            'membership' => [
                'id' => (string) $membership->getId(),
                'projectId' => (string) $project->getId(),
                'role' => $membership->getRole()->value,
                'created' => $createdMembership,
            ],
        ], $createdUser || $createdMembership ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
