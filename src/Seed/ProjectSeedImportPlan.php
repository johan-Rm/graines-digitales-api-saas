<?php

namespace App\Seed;

final class ProjectSeedImportPlan
{
    /**
     * @param list<string> $families
     * @param array<string, list<string>> $dependencies
     */
    public function __construct(
        public readonly array $families,
        public readonly array $dependencies,
    ) {
    }
}
