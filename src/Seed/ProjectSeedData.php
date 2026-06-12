<?php

namespace App\Seed;

final class ProjectSeedData
{
    /**
     * @param list<array{codeValue: string, name: string, inCodeSet: string, text?: string|null, source: string}> $categoryCodes
     * @param list<array{url: string, filename: string, caption: string|null, mainEntity: string|null, representativeOfPage: bool|null, sourcePath: string, checksum: string|null, mimeType: string|null, size: int|null}> $mediaObjects
     * @param list<array{slug: string, locale: string, metaTitle: string|null, metaDescription: string|null, headline: string|null, body: string, associatedMedia: list<string>, source: string}> $webPages
     * @param list<array{identifier: string|null, slug: string, locale: string, name: string|null, category: string, place: string, realEstateListing: string, amenityFeature: list<string>, tags: list<string>, associatedMedia: list<array{image: string, caption: string|null, keywords: list<string>, representativeOfPage: bool|null}>, hasPart: list<array{additionalType: string, position: int|null, name: string|null, headline: string|null, text: string|null, associatedMedia: list<string>, meta: array<string, mixed>}>, body: string, metaTitle: string|null, metaDescription: string|null, highlight: string|null, review: string|null, offer: array<string, mixed>, fields: array<string, mixed>, source: string}> $accommodations
     * @param list<string> $ignoredFiles
     */
    public function __construct(
        public readonly string $rootPath,
        public readonly string $locale,
        public readonly array $categoryCodes,
        public readonly array $mediaObjects,
        public readonly array $webPages,
        public readonly array $accommodations,
        public readonly array $ignoredFiles = [],
    ) {
    }

    public function countFamily(string $family): int
    {
        return match ($family) {
            ProjectSeedFamily::METADATA => count($this->categoryCodes),
            ProjectSeedFamily::MEDIA => count($this->mediaObjects),
            ProjectSeedFamily::WEB_PAGES => count($this->webPages),
            ProjectSeedFamily::ACCOMMODATIONS => count($this->accommodations),
            default => 0,
        };
    }
}
