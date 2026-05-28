<?php

namespace App\Seed;

final class ProjectSeedImportResult
{
    /**
     * @param array<string, int> $imported
     */
    public function __construct(public readonly array $imported)
    {
    }
}
