<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OrganizationByOwnerEmailProvider implements ProviderInterface
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
            throw new NotFoundHttpException('No organization found for this owner email.');
        }

        $membership = $this->entityManager->getRepository(OrganizationMember::class)->findOneBy([
            'user' => $user,
            'role' => OrganizationRole::Owner,
        ]);

        if (!$membership instanceof OrganizationMember) {
            throw new NotFoundHttpException('No organization found for this owner email.');
        }

        $organization = $membership->getOrganization();
        if (!$organization instanceof Organization) {
            throw new NotFoundHttpException('No organization found for this owner email.');
        }

        return $organization;
    }
}
