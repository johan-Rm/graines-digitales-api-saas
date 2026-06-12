<?php

namespace App\Seed;

use App\Entity\Accommodation;
use App\Entity\CategoryCode;
use App\Entity\MediaObject;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectSeedLoader
{
    public function __construct(
        private ProjectSeedSourceAnalyzer $sourceAnalyzer,
        private ProjectSeedImportPlanner $planner,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function analyze(Project $project, string $projectPath): ProjectSeedReport
    {
        return $this->sourceAnalyzer->analyze($project, $projectPath);
    }

    /**
     * @param list<string> $selectedFamilies
     *
     * @return list<string>
     */
    public function validateSelection(Project $project, ProjectSeedData $data, array $selectedFamilies): array
    {
        $errors = [];
        $selected = array_fill_keys($selectedFamilies, true);
        $availableCategoryCodes = array_fill_keys($this->existingCategoryCodes($project), true);
        $availableMedia = array_fill_keys($this->existingMediaReferences($project), true);

        if (isset($selected[ProjectSeedFamily::METADATA])) {
            foreach ($data->categoryCodes as $categoryCode) {
                $availableCategoryCodes[$categoryCode['inCodeSet'].'/'.$categoryCode['codeValue']] = true;
            }
        }

        if (isset($selected[ProjectSeedFamily::MEDIA])) {
            foreach ($data->mediaObjects as $mediaObject) {
                if (!is_file($mediaObject['sourcePath'])) {
                    $errors[] = sprintf('Cannot import media without source file for "%s": %s', $mediaObject['url'], $mediaObject['sourcePath']);
                }

                $availableMedia[$mediaObject['filename']] = true;
                $availableMedia[$this->basenameWithoutExtension($mediaObject['filename'])] = true;
                $availableMedia['media-'.$this->basenameWithoutExtension($mediaObject['filename'])] = true;
            }
        }

        if (isset($selected[ProjectSeedFamily::WEB_PAGES])) {
            foreach ($data->webPages as $webPage) {
                foreach ($webPage['associatedMedia'] as $reference) {
                    if (!isset($availableMedia[$this->normalizeMediaReference($reference)])) {
                        $errors[] = sprintf(
                            'Cannot import web-pages because media reference "%s" from %s is not available in the selected seed data or existing project media.',
                            $reference,
                            $webPage['source'],
                        );
                    }
                }
            }
        }

        if (isset($selected[ProjectSeedFamily::ACCOMMODATIONS])) {
            foreach ($data->accommodations as $accommodation) {
                $this->validateAccommodation($errors, $availableCategoryCodes, $availableMedia, $accommodation);
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param list<string>        $errors
     * @param array<string, true> $availableCategoryCodes
     * @param array<string, true> $availableMedia
     * @param array{category: string, place: string, realEstateListing: string, amenityFeature: list<string>, associatedMedia: list<array{image: string}>, hasPart: list<array{associatedMedia: list<string>}>, source: string} $accommodation
     */
    private function validateAccommodation(array &$errors, array $availableCategoryCodes, array $availableMedia, array $accommodation): void
    {
        $this->requireCategoryCode($errors, $availableCategoryCodes, Accommodation::CATEGORY_CODE_SET, $accommodation['category'], $accommodation['source'], 'category');
        $this->requireCategoryCode($errors, $availableCategoryCodes, Accommodation::PLACE_CODE_SET, $accommodation['place'], $accommodation['source'], 'place');
        $this->requireCategoryCode($errors, $availableCategoryCodes, Accommodation::REAL_ESTATE_LISTING_CODE_SET, $accommodation['realEstateListing'], $accommodation['source'], 'realEstateListing');

        foreach ($accommodation['amenityFeature'] as $amenityFeature) {
            $this->requireCategoryCode($errors, $availableCategoryCodes, Accommodation::AMENITY_FEATURE_CODE_SET, $amenityFeature, $accommodation['source'], 'amenityFeature');
        }

        foreach ($accommodation['associatedMedia'] as $edge) {
            $this->requireMediaReference($errors, $availableMedia, $edge['image'], $accommodation['source'], 'media');
        }

        foreach ($accommodation['hasPart'] as $block) {
            foreach ($block['associatedMedia'] as $reference) {
                $this->requireMediaReference($errors, $availableMedia, $reference, $accommodation['source'], 'hasPart media');
            }
        }
    }

    /**
     * @param list<string>        $errors
     * @param array<string, true> $availableMedia
     */
    private function requireMediaReference(array &$errors, array $availableMedia, string $reference, string $source, string $kind): void
    {
        if (!isset($availableMedia[$this->normalizeMediaReference($reference)])) {
            $errors[] = sprintf(
                'Cannot import accommodations because %s reference "%s" from %s is not available in the selected seed data or existing project media.',
                $kind,
                $reference,
                $source,
            );
        }
    }

    /**
     * @param list<string> $selectedFamilies
     */
    public function plan(ProjectSeedData $data, array $selectedFamilies): ProjectSeedImportPlan
    {
        return $this->planner->build($data, $selectedFamilies);
    }

    public function import(Project $project, ProjectSeedData $data, ProjectSeedImportPlan $plan): ProjectSeedImportResult
    {
        $importers = $this->planner->importersByFamily();
        $imported = [];

        $this->entityManager->wrapInTransaction(function () use ($project, $data, $plan, $importers, &$imported): void {
            foreach ($plan->families as $family) {
                $importer = $importers[$family] ?? null;
                if (!$importer instanceof ProjectSeedImporterInterface) {
                    throw new \RuntimeException(sprintf('Missing importer for seed family "%s".', $family));
                }

                $imported[$family] = $importer->import($project, $data);
                $this->entityManager->flush();
            }
        });

        return new ProjectSeedImportResult($imported);
    }

    /**
     * @return list<string>
     */
    private function existingCategoryCodes(Project $project): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('categoryCode.inCodeSet', 'categoryCode.codeValue')
            ->from(CategoryCode::class, 'categoryCode')
            ->where('categoryCode.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['inCodeSet'].'/'.$row['codeValue'], $rows);
    }

    /**
     * @return list<string>
     */
    private function existingMediaReferences(Project $project): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('mediaObject.filePath')
            ->from(MediaObject::class, 'mediaObject')
            ->where('mediaObject.project = :project')
            ->andWhere('mediaObject.filePath IS NOT NULL')
            ->setParameter('project', $project)
            ->getQuery()
            ->getArrayResult();

        $references = [];
        foreach ($rows as $row) {
            $filename = (string) $row['filePath'];
            $references[] = $filename;
            $references[] = $this->basenameWithoutExtension($filename);
            $references[] = 'media-'.$this->basenameWithoutExtension($filename);
        }

        return $references;
    }

    /**
     * @param list<string> $errors
     */
    private function requireCategoryCode(array &$errors, array $categoryCodes, string $codeSet, string $code, string $source, string $field): void
    {
        if ('' === $code || !isset($categoryCodes[$codeSet.'/'.$code])) {
            $errors[] = sprintf(
                'Cannot import accommodations because CategoryCode "%s" in code set "%s" from %s field %s is not available in the selected seed data or existing project metadata.',
                $code,
                $codeSet,
                $source,
                $field,
            );
        }
    }

    private function normalizeMediaReference(string $reference): string
    {
        return str_starts_with($reference, 'media-') ? $reference : ltrim($reference, '/');
    }

    private function basenameWithoutExtension(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }
}
