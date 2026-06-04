<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Entity\CategoryCode;
use App\Exception\CategoryCodeInUseException;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CategoryCodeRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof CategoryCode) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $this->projectAccessGuard->getWritableProject((string) ($uriVariables['projectId'] ?? ''));
        $usageCount = $this->countAccommodationsUsing($data);

        if ($usageCount > 0) {
            throw new CategoryCodeInUseException($usageCount);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function countAccommodationsUsing(CategoryCode $categoryCode): int
    {
        $project = $categoryCode->getProject();

        if (null === $project) {
            return 0;
        }

        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT accommodation.id)')
            ->from(Accommodation::class, 'accommodation')
            ->leftJoin('accommodation.amenityFeature', 'amenityFeature')
            ->leftJoin('accommodation.tags', 'tag')
            ->where('accommodation.project = :project')
            ->andWhere('accommodation.category = :categoryCode OR accommodation.realEstateListing = :categoryCode OR accommodation.place = :categoryCode OR amenityFeature = :categoryCode OR tag = :categoryCode')
            ->setParameter('project', $project)
            ->setParameter('categoryCode', $categoryCode)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
