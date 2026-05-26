<?php

namespace App\State;

use App\Entity\Accommodation;
use App\Entity\CategoryCode;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AccommodationIdentifierGenerator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function generate(Accommodation $accommodation): string
    {
        $project = $accommodation->getProject();
        $realEstateListing = $accommodation->getRealEstateListing();
        $category = $accommodation->getCategory();

        if (!$project instanceof Project || !$realEstateListing instanceof CategoryCode || !$category instanceof CategoryCode) {
            throw new BadRequestHttpException('Accommodation identifier requires project, realEstateListing and category.');
        }

        $prefix = self::initials($realEstateListing->getCode()).self::initials($category->getCode());
        $identifiers = $this->existingPairIdentifiers($project, $realEstateListing, $category);
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
     * @return list<string>
     */
    private function existingPairIdentifiers(Project $project, CategoryCode $realEstateListing, CategoryCode $category): array
    {
        $identifiers = $this->entityManager->createQueryBuilder()
            ->select('accommodation.identifier')
            ->from(Accommodation::class, 'accommodation')
            ->andWhere('accommodation.project = :project')
            ->andWhere('accommodation.realEstateListing = :realEstateListing')
            ->andWhere('accommodation.category = :category')
            ->andWhere('accommodation.deletedAt IS NULL')
            ->setParameter('project', $project)
            ->setParameter('realEstateListing', $realEstateListing)
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($identifiers, 'is_string'));
    }
}
