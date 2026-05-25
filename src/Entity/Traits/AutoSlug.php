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

    protected static function buildSlug(string ...$parts): string
    {
        $joined = implode(' ', array_filter($parts, static fn (string $p): bool => '' !== trim($p)));
        // Strip apostrophes before slugifying to avoid spurious dashes
        $joined = str_replace(["'", "\u{2019}", "\u{2018}"], '', $joined);

        return self::normalizeSlug($joined);
    }
}
