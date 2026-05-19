<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateLocalUserController extends AbstractController
{
    private const ALLOWED_ENVIRONMENTS = ['dev', 'test'];
    private const ALLOWED_ROLES = ['ROLE_USER', 'ROLE_ADMIN'];

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $kernelEnvironment,
    ) {
    }

    #[Route('/api/auth/local-users', name: 'api_auth_local_users_create', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
    ): JsonResponse {
        if (!in_array($this->kernelEnvironment, self::ALLOWED_ENVIRONMENTS, true)) {
            return $this->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $validator->validate($payload, new Assert\Collection(
            fields: [
                'email' => new Assert\Required([
                    new Assert\NotBlank(),
                    new Assert\Email(),
                    new Assert\Length(max: 180),
                ]),
                'password' => new Assert\Required([
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8, max: 4096),
                ]),
                'roles' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Type('string'),
                        new Assert\Choice(choices: self::ALLOWED_ROLES),
                    ]),
                    new Assert\Count(min: 1),
                ]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));

        if (count($violations) > 0) {
            return $this->json([
                'message' => 'Invalid user payload.',
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
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser instanceof User) {
            return $this->json(['message' => 'A user already exists with this email.'], Response::HTTP_CONFLICT);
        }

        $roles = $payload['roles'] ?? ['ROLE_ADMIN'];

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(array_values(array_unique($roles)));
        $user->setPassword($passwordHasher->hashPassword($user, (string) $payload['password']));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }
}
