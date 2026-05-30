<?php

namespace App\Seed;

use App\Entity\CategoryCode;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CategoryCodeSeedImporter implements ProjectSeedImporterInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function family(): string
    {
        return ProjectSeedFamily::METADATA;
    }

    public function requires(ProjectSeedData $data): array
    {
        return [];
    }

    public function import(Project $project, ProjectSeedData $data): int
    {
        $count = 0;

        foreach ($data->categoryCodes as $source) {
            $categoryCode = $this->entityManager->getRepository(CategoryCode::class)->findOneBy([
                'project' => $project,
                'inCodeSet' => $source['inCodeSet'],
                'codeValue' => $source['codeValue'],
            ]);

            if (!$categoryCode instanceof CategoryCode) {
                $categoryCode = (new CategoryCode())
                    ->setProject($project)
                    ->setCodeValue($source['codeValue'])
                    ->setInCodeSet($source['inCodeSet']);
            }

            $categoryCode->setCurrentLocale($data->locale);
            $categoryCode->setLabel($source['name']);
            $categoryCode->setMetadata(null === ($source['text'] ?? null) ? null : ['text' => $source['text']]);

            $this->entityManager->persist($categoryCode);
            $count++;
        }

        return $count;
    }
}
