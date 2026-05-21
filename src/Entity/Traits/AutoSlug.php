<?php

namespace App\Entity\Traits;

use Symfony\Component\String\Slugger\AsciiSlugger;

trait AutoSlug
{
    private static function normalizeSlug(?string $value): string
    {
        $value = trim((string) $value);

        if ('' === $value) {
            return '';
        }

        return (new AsciiSlugger('fr'))->slug($value)->lower()->toString();
    }
}
