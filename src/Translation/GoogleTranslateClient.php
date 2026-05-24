<?php

namespace App\Translation;

final readonly class GoogleTranslateClient
{
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        return $text;
    }
}
