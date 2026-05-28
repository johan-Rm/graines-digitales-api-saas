<?php

namespace App\Seed;

use App\Entity\Project;

interface ProjectSeedImporterInterface
{
    public function family(): string;

    /**
     * @return list<string>
     */
    public function requires(ProjectSeedData $data): array;

    public function import(Project $project, ProjectSeedData $data): int;
}
