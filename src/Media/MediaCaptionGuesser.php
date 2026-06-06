<?php

namespace App\Media;

final class MediaCaptionGuesser
{
    public function guessFromFilename(string $filename): ?string
    {
        $name = pathinfo(urldecode($filename), PATHINFO_FILENAME);
        $name = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $name) ?? $name;
        $name = str_replace(['-', '_', '.'], ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);

        if ('' === $name) {
            return null;
        }

        return ucfirst(mb_strtolower($name));
    }
}
