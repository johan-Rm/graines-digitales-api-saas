<?php

namespace App\Seed;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ProjectSeedImportPlanner
{
    /**
     * @param iterable<ProjectSeedImporterInterface> $importers
     */
    public function __construct(
        #[AutowireIterator('app.project_seed_importer')]
        private iterable $importers,
    )
    {
    }

    /**
     * @param list<string> $selectedFamilies
     */
    public function build(ProjectSeedData $data, array $selectedFamilies): ProjectSeedImportPlan
    {
        $selected = array_values(array_unique($selectedFamilies));
        $importers = $this->importersByFamily();
        $dependencies = [];

        foreach ($selected as $family) {
            if (!isset($importers[$family])) {
                throw new \InvalidArgumentException(sprintf('Unknown seed import family "%s".', $family));
            }

            $dependencies[$family] = array_values(array_filter(
                $importers[$family]->requires($data),
                static fn (string $dependency): bool => in_array($dependency, $selected, true),
            ));
        }

        return new ProjectSeedImportPlan($this->sort($selected, $dependencies), $dependencies);
    }

    /**
     * @return array<string, ProjectSeedImporterInterface>
     */
    public function importersByFamily(): array
    {
        $indexed = [];

        foreach ($this->importers as $importer) {
            $indexed[$importer->family()] = $importer;
        }

        return $indexed;
    }

    /**
     * @param list<string> $families
     * @param array<string, list<string>> $dependencies
     *
     * @return list<string>
     */
    private function sort(array $families, array $dependencies): array
    {
        $pending = array_fill_keys($families, true);
        $sorted = [];

        while ([] !== $pending) {
            $progress = false;

            foreach (array_keys($pending) as $family) {
                $missing = array_filter(
                    $dependencies[$family] ?? [],
                    static fn (string $dependency): bool => !in_array($dependency, $sorted, true),
                );

                if ([] !== $missing) {
                    continue;
                }

                $sorted[] = $family;
                unset($pending[$family]);
                $progress = true;
            }

            if (!$progress) {
                throw new \RuntimeException('Unable to build seed import plan because dependencies are cyclic.');
            }
        }

        return $sorted;
    }
}
