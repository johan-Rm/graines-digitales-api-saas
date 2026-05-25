<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Security\OrganizationAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListOrganizationMembersController
{
    public function __construct(
        private OrganizationAccessGuard $organizationAccessGuard,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/organizations/{organizationId}/members', name: 'api_organization_members_list', methods: ['GET'])]
    public function __invoke(string $organizationId): JsonResponse
    {
        $organization = $this->entityManager->getRepository(Organization::class)->find($organizationId);
        if (!$organization instanceof Organization) {
            throw new NotFoundHttpException('Organization not found.');
        }

        $this->organizationAccessGuard->assertCanManageOrganizationMembers($organization);

        $members = $this->entityManager->getRepository(OrganizationMember::class)->findBy([
            'organization' => $organization,
        ]);

        return new JsonResponse(
            array_map(static fn (OrganizationMember $member): array => [
                'id' => (string) $member->getId(),
                'role' => $member->getRole()->value,
                'user' => [
                    'id' => (string) $member->getUser()?->getId(),
                    'email' => $member->getUser()?->getEmail(),
                ],
                'createdAt' => $member->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $member->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ], $members),
            Response::HTTP_OK
        );
    }
}
