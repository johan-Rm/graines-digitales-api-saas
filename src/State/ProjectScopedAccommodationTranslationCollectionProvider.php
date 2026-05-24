<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\AccommodationTranslation;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectScopedAccommodationTranslationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getReadableProject($projectId);

        return $this->entityManager->createQueryBuilder()
            ->select('translation')
            ->from(AccommodationTranslation::class, 'translation')
            ->innerJoin('translation.translatable', 'accommodation')
            ->andWhere('translation.project = :project')
            ->andWhere('accommodation.deletedAt IS NULL')
            ->setParameter('project', $project)
            ->orderBy('accommodation.identifier', 'ASC')
            ->addOrderBy('translation.locale', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
