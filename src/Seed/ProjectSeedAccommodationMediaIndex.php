<?php

namespace App\Seed;

/**
 * Resolves which seed images belong to a real-estate listing (accommodation).
 *
 * Watermarking is a business rule scoped to property images: only media
 * referenced by an accommodation (directly via associatedMedia or through a
 * hasPart content block) must receive the watermark. Generic media and
 * web-page assets are delivered untouched.
 *
 * Reference matching mirrors the normalization used by ProjectSeedLoader so a
 * single image can be referenced by full filename, basename, or "media-" prefix.
 */
final readonly class ProjectSeedAccommodationMediaIndex
{
    /**
     * @param array<string, true> $references normalized lookup keys owned by accommodations
     */
    private function __construct(private array $references)
    {
    }

    public static function fromSeedData(ProjectSeedData $data): self
    {
        $references = [];

        foreach ($data->accommodations as $accommodation) {
            foreach ($accommodation['associatedMedia'] as $edge) {
                self::collect($references, $edge['image']);
            }

            foreach ($accommodation['hasPart'] as $block) {
                foreach ($block['associatedMedia'] as $reference) {
                    self::collect($references, $reference);
                }
            }
        }

        return new self($references);
    }

    public function covers(string $reference): bool
    {
        foreach (self::candidateKeys($reference) as $key) {
            if (isset($this->references[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, true> $references
     */
    private static function collect(array &$references, string $reference): void
    {
        foreach (self::candidateKeys($reference) as $key) {
            $references[$key] = true;
        }
    }

    /**
     * @return list<string>
     */
    private static function candidateKeys(string $reference): array
    {
        $normalized = self::normalize($reference);
        if ('' === $normalized) {
            return [];
        }

        $basename = pathinfo($normalized, PATHINFO_FILENAME);

        return array_values(array_unique(array_filter(
            [
                $normalized,
                $basename,
                '' === $basename ? '' : 'media-'.$basename,
            ],
            static fn (string $candidate): bool => '' !== $candidate,
        )));
    }

    private static function normalize(string $reference): string
    {
        $reference = trim($reference);

        return str_starts_with($reference, 'media-') ? $reference : ltrim($reference, '/');
    }
}
