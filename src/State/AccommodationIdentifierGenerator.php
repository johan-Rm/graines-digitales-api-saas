<?php

namespace App\State;

use App\Entity\Accommodation;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AccommodationIdentifierGenerator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function generate(Accommodation $accommodation, ?Accommodation $excludedAccommodation = null): string
    {
        $project = $accommodation->getProject();
        if (!$project instanceof Project || !$accommodation->hasRealEstateListing() || !$accommodation->hasCategory()) {
            throw new BadRequestHttpException('Accommodation identifier requires project, realEstateListing and category.');
        }

        $realEstateListing = $accommodation->getRealEstateListing();
        $category = $accommodation->getCategory();

        $prefix = self::initials($realEstateListing->getCode()).self::initials($category->getCode());
        $identifiers = $this->existingPrefixIdentifiers($project, $prefix, $excludedAccommodation);
        $maxSuffix = 0;

        foreach ($identifiers as $identifier) {
            if (1 === preg_match('/^'.preg_quote($prefix, '/').'(\d{3,})$/', $identifier, $matches)) {
                $maxSuffix = max($maxSuffix, (int) $matches[1]);
            }
        }

        $nextCounter = max(\count($identifiers), $maxSuffix) + 1;

        return sprintf('%s%03d', $prefix, $nextCounter);
    }

    private static function initials(string $code): string
    {
        $words = preg_split('/-+/', trim($code));

        if (!\is_array($words)) {
            return '';
        }

        return strtoupper(implode('', array_map(
            static fn (string $word): string => '' !== $word ? $word[0] : '',
            array_filter($words, static fn (string $word): bool => '' !== $word),
        )));
    }

    /**
     * Returns all identifiers in the project matching the given prefix,
     * including soft-deleted ones — the unique constraint on (project_id, identifier)
     * applies regardless of deleted_at, so we must never reuse a deleted identifier.
     *
     * @return list<string>
     */
    private function existingPrefixIdentifiers(Project $project, string $prefix, ?Accommodation $excludedAccommodation): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('accommodation.identifier')
            ->from(Accommodation::class, 'accommodation')
            ->andWhere('accommodation.project = :project')
            ->andWhere('accommodation.identifier LIKE :prefix')
            ->setParameter('project', $project)
            ->setParameter('prefix', $prefix.'%');

        if (null !== $excludedAccommodation?->getId()) {
            $queryBuilder
                ->andWhere('accommodation.id != :excludedAccommodationId')
                ->setParameter('excludedAccommodationId', $excludedAccommodation->getId());
        }

        $identifiers = $queryBuilder
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($identifiers, 'is_string'));
    }
}
