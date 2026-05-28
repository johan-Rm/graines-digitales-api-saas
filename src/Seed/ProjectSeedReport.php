<?php

namespace App\Seed;

final class ProjectSeedReport
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly ProjectSeedData $data,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }
}
