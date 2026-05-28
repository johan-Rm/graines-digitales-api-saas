<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Security\CurrentUserProvider;
use App\Security\PlatformSuperAdminResolver;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrganizationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
        private PlatformSuperAdminResolver $superAdminResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $user = $this->currentUserProvider->getUser();

        if ($this->superAdminResolver->isSuperAdmin($user)) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        if (!$user instanceof User) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(Organization::class, 'o')
            ->join(OrganizationMember::class, 'om', 'WITH', 'om.organization = o AND om.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
