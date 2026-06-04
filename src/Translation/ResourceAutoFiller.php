<?php

namespace App\Translation;

use App\Entity\ResourceTranslation;

final class ResourceAutoFiller
{
    public function fill(ResourceTranslation $translation): void
    {
        $reflection = new \ReflectionClass($translation);

        foreach ($reflection->getProperties() as $property) {
            $attrs = $property->getAttributes(AutoFill::class);
            if ([] === $attrs) {
                continue;
            }

            /** @var AutoFill $autoFill */
            $autoFill = $attrs[0]->newInstance();
            $field = $property->getName();
            $getter = 'get'.ucfirst($field);
            $setter = 'set'.ucfirst($field);
            $sourceGetter = 'get'.ucfirst($autoFill->from);

            $currentValue = $translation->{$getter}();
            if (is_string($currentValue) && '' !== trim($currentValue)) {
                continue;
            }

            $sourceValue = $translation->{$sourceGetter}();
            if (!is_string($sourceValue) || '' === trim($sourceValue)) {
                continue;
            }

            $text = $autoFill->markdownExcerpt
                ? $this->extractMarkdownExcerpt($sourceValue)
                : strip_tags($sourceValue);

            $value = null !== $autoFill->maxLength
                ? rtrim(mb_substr($text, 0, $autoFill->maxLength))
                : $text;

            $translation->{$setter}($value);
        }
    }

    private function extractMarkdownExcerpt(string $markdown): string
    {
        // Split on blank lines, take first non-empty paragraph
        $paragraphs = preg_split('/\n{2,}/', trim($markdown)) ?: [];
        $paragraph = '';
        foreach ($paragraphs as $chunk) {
            $chunk = trim($chunk);
            if ('' !== $chunk) {
                $paragraph = $chunk;
                break;
            }
        }

        if ('' === $paragraph) {
            return '';
        }

        // Strip markdown syntax, keep readable text
        $plain = $paragraph;
        $plain = preg_replace('/^#{1,6}\s+/m', '', $plain) ?? $plain;         // headers
        $plain = preg_replace('/!\[.*?\]\(.*?\)/', '', $plain) ?? $plain;      // images
        $plain = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $plain) ?? $plain; // links
        $plain = preg_replace('/\*{1,3}([^*]+)\*{1,3}/', '$1', $plain) ?? $plain; // bold/italic *
        $plain = preg_replace('/_{1,3}([^_]+)_{1,3}/', '$1', $plain) ?? $plain;   // bold/italic _
        $plain = preg_replace('/`([^`]+)`/', '$1', $plain) ?? $plain;          // inline code
        $plain = strip_tags($plain);
        $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;                 // normalize whitespace

        return trim($plain);
    }
}
