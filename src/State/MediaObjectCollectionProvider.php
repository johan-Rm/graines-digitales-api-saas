<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\MediaObject;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Security\CurrentUserProvider;
use App\Security\PlatformSuperAdminResolver;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MediaObjectCollectionProvider implements ProviderInterface
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
            ->select('m')
            ->from(MediaObject::class, 'm')
            ->join('m.project', 'p')
            ->join(OrganizationMember::class, 'om', 'WITH', 'om.organization = p.organization AND om.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
