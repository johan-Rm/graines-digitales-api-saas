<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Security\OrganizationAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CreateOrganizationMemberController
{
    public function __construct(
        private OrganizationAccessGuard $organizationAccessGuard,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/organizations/{organizationId}/members', name: 'api_organization_members_create', methods: ['POST'])]
    public function __invoke(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->entityManager->getRepository(Organization::class)->find($organizationId);
        if (!$organization instanceof Organization) {
            throw new NotFoundHttpException('Organization not found.');
        }

        $this->organizationAccessGuard->assertCanManageOrganizationMembers($organization);

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
                        static fn (OrganizationRole $role): string => $role->value,
                        OrganizationRole::cases(),
                    )),
                ]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));

        if (count($violations) > 0) {
            return new JsonResponse([
                'message' => 'Invalid organization member payload.',
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
        $organizationRole = OrganizationRole::from((string) ($payload['role'] ?? OrganizationRole::Member->value));

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $userCreated = false;

        if (!$user instanceof User) {
            if (!isset($payload['password'])) {
                return new JsonResponse([
                    'message' => 'A password is required to create a new user.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, (string) $payload['password']));
            $this->entityManager->persist($user);
            $userCreated = true;
        }

        $membership = $this->entityManager->getRepository(OrganizationMember::class)->findOneBy([
            'organization' => $organization,
            'user' => $user,
        ]);
        $membershipCreated = false;

        if (!$membership instanceof OrganizationMember) {
            $membership = new OrganizationMember();
            $membership->setOrganization($organization);
            $membership->setUser($user);
            $membership->markCreatedBy($user);
            $this->entityManager->persist($membership);
            $membershipCreated = true;
        }

        $membership->setRole($organizationRole);
        $membership->touch($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'user' => [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'created' => $userCreated,
            ],
            'membership' => [
                'id' => (string) $membership->getId(),
                'organizationId' => (string) $organization->getId(),
                'role' => $membership->getRole()->value,
                'created' => $membershipCreated,
            ],
        ], $membershipCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
