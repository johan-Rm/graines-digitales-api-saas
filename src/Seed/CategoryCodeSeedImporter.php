<?php

namespace App\Seed;

use App\Entity\CategoryCode;
use App\Entity\Project;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CategoryCodeSeedImporter implements ProjectSeedImporterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResourceAutoTranslator $autoTranslator,
    ) {
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
        $imported = [];

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
            $categoryCode->setText($source['text'] ?? null);
            if (array_key_exists('isEnabled', $source) && is_bool($source['isEnabled'])) {
                $categoryCode->setMetadata(array_merge($categoryCode->getMetadata() ?? [], [
                    'isEnabled' => $source['isEnabled'],
                ]));
            }
            $this->clearTranslatedTexts($categoryCode, $data->locale);

            $this->entityManager->persist($categoryCode);
            $imported[] = $categoryCode;
            $count++;
        }

        // Flush source-locale data before creating translations for other locales,
        // so that lazy-loaded collections are consistent when synchronize runs.
        $this->entityManager->flush();
        foreach ($imported as $categoryCode) {
            $this->autoTranslator->synchronize($categoryCode, $project);
        }

        return $count;
    }

    private function clearTranslatedTexts(CategoryCode $categoryCode, string $sourceLocale): void
    {
        foreach ($categoryCode->getTranslations() as $translation) {
            if ($translation->getLocale() !== $sourceLocale && method_exists($translation, 'setText')) {
                $translation->setText(null);
            }
        }
    }
}
