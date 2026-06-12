<?php

namespace App\Seed;

use App\Entity\Accommodation;
use App\Entity\Project;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ProjectSeedSourceAnalyzer
{
    public function analyze(Project $project, string $projectPath): ProjectSeedReport
    {
        $errors = [];
        $warnings = [];
        $locale = $project->getSourceLocale();
        $dataPath = $projectPath.'/data/'.$locale;
        $metadataPath = $dataPath.'/metadata';
        $webPagesPath = $dataPath.'/web-pages';
        $accommodationsPath = $dataPath.'/accommodations';
        $imagesPath = $projectPath.'/resources/images';

        foreach ([
            'project root' => $projectPath,
            'metadata' => $metadataPath,
            'web-pages' => $webPagesPath,
            'accommodations' => $accommodationsPath,
            'images' => $imagesPath,
        ] as $label => $path) {
            if (!is_dir($path)) {
                $errors[] = sprintf('Missing %s directory: %s', $label, $path);
            }
        }

        if ([] !== $errors) {
            return new ProjectSeedReport(new ProjectSeedData($projectPath, $locale, [], [], [], []), $errors);
        }

        try {
            $categoryCodes = $this->loadCategoryCodes($metadataPath.'/category-code.yaml');
            $mediaObjects = $this->loadMediaObjects($metadataPath.'/media-object.yaml', $imagesPath);
            $webPages = $this->loadWebPages($webPagesPath, $locale);
            $accommodations = $this->loadAccommodations($accommodationsPath, $locale);
        } catch (ParseException|\UnexpectedValueException $exception) {
            return new ProjectSeedReport(
                new ProjectSeedData($projectPath, $locale, [], [], [], []),
                [$exception->getMessage()],
            );
        }

        $data = new ProjectSeedData(
            $projectPath,
            $locale,
            $categoryCodes,
            $mediaObjects,
            $webPages,
            $accommodations,
            $this->findIgnoredFiles($dataPath),
        );

        $warnings = array_merge($warnings, $this->detectWarnings($data));

        return new ProjectSeedReport($data, $errors, $warnings);
    }

    /**
     * @return list<array{codeValue: string, name: string, inCodeSet: string, text?: string|null, source: string}>
     */
    private function loadCategoryCodes(string $path): array
    {
        $items = $this->parseYamlItems($path);
        $categoryCodes = [];

        foreach ($items as $index => $item) {
            $codeValue = $this->stringValue($item, 'codeValue');
            $name = $this->stringValue($item, 'name');
            $inCodeSet = $this->normalizeCodeSet($this->stringValue($item, 'inCodeSet'));

            if ('' === $codeValue || '' === $name || '' === $inCodeSet) {
                throw new \UnexpectedValueException(sprintf('Invalid category-code item #%d in %s.', $index + 1, $path));
            }

            $categoryCodes[] = [
                'codeValue' => $codeValue,
                'name' => $name,
                'inCodeSet' => $inCodeSet,
                'text' => $this->nullableStringValue($item, 'text'),
                'source' => $path,
            ];
        }

        return $categoryCodes;
    }

    /**
     * @return list<array{url: string, filename: string, caption: string|null, mainEntity: string|null, representativeOfPage: bool|null, sourcePath: string, checksum: string|null, mimeType: string|null, size: int|null}>
     */
    private function loadMediaObjects(string $path, string $imagesPath): array
    {
        $items = $this->parseYamlItems($path);
        $mediaObjects = [];

        foreach ($items as $index => $item) {
            $entry = $this->buildMediaObjectEntry($item, $index, $path, $imagesPath);
            if (null !== $entry) {
                $mediaObjects[] = $entry;
            }
        }

        return $mediaObjects;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{url: string, filename: string, caption: string|null, mainEntity: string|null, representativeOfPage: bool|null, sourcePath: string, checksum: string|null, mimeType: string|null, size: int|null}|null
     */
    private function buildMediaObjectEntry(array $item, int $index, string $path, string $imagesPath): ?array
    {
        $url = $this->stringValue($item, 'url');
        if ('' === $url) {
            throw new \UnexpectedValueException(sprintf('Invalid media-object item #%d in %s: missing url.', $index + 1, $path));
        }

        if (!$this->isLocalMediaUrl($url)) {
            return null;
        }

        $filename = $this->normalizeMediaReference($url);
        $sourcePath = $imagesPath.'/'.$filename;
        $representativeOfPage = isset($item['representativeOfPage']) ? (bool) $item['representativeOfPage'] : null;
        $checksum = null;
        $mimeType = null;
        $size = null;
        if (is_file($sourcePath)) {
            $checksum = hash_file('sha256', $sourcePath) ?: null;
            $mimeType = mime_content_type($sourcePath) ?: null;
            $size = filesize($sourcePath) ?: null;
        }

        return [
            'url' => $url,
            'filename' => $filename,
            'caption' => $this->nullableStringValue($item, 'caption'),
            'mainEntity' => $this->nullableStringValue($item, 'mainEntity'),
            'representativeOfPage' => $representativeOfPage,
            'sourcePath' => $sourcePath,
            'checksum' => $checksum,
            'mimeType' => $mimeType,
            'size' => $size,
        ];
    }

    /**
     * @return list<array{slug: string, locale: string, metaTitle: string|null, metaDescription: string|null, headline: string|null, body: string, associatedMedia: list<string>, source: string}>
     */
    private function loadWebPages(string $path, string $locale): array
    {
        $webPages = [];

        foreach ($this->markdownFiles($path) as $filePath) {
            [$frontMatter, $body] = $this->parseMarkdown($filePath);
            $webPages[] = [
                'slug' => basename($filePath, '.md'),
                'locale' => $this->nullableStringValue($frontMatter, 'inLanguage') ?? $locale,
                'metaTitle' => $this->nullableStringValue($frontMatter, 'metaTitle'),
                'metaDescription' => $this->nullableStringValue($frontMatter, 'metaDescription'),
                'headline' => $this->nullableStringValue($frontMatter, 'headline'),
                'body' => $body,
                'associatedMedia' => $this->stringList($frontMatter['associatedMedia'] ?? []),
                'source' => $filePath,
            ];
        }

        return $webPages;
    }

    /**
     * @return list<array{identifier: string|null, slug: string, locale: string, name: string|null, category: string, place: string, realEstateListing: string, amenityFeature: list<string>, tags: list<string>, associatedMedia: list<array{image: string, caption: string|null, keywords: list<string>}>, hasPart: list<array{additionalType: string, position: int|null, name: string|null, headline: string|null, text: string|null, associatedMedia: list<string>, meta: array<string, mixed>}>, body: string, metaTitle: string|null, metaDescription: string|null, highlight: string|null, review: string|null, offer: array<string, mixed>, fields: array<string, mixed>, source: string}>
     */
    private function loadAccommodations(string $path, string $locale): array
    {
        $accommodations = [];

        foreach ($this->markdownFiles($path) as $filePath) {
            [$frontMatter, $body] = $this->parseMarkdown($filePath);
            $slug = $this->nullableStringValue($frontMatter, 'slug') ?? basename($filePath, '.md');
            $identifier = $this->nullableStringValue($frontMatter, 'identifier')
                ?? $this->identifierFromFilePathOrSlug($filePath, $slug);

            $accommodations[] = [
                'identifier' => $identifier,
                'slug' => $slug,
                'locale' => $this->nullableStringValue($frontMatter, 'inLanguage') ?? $locale,
                'name' => $this->nullableStringValue($frontMatter, 'name'),
                'category' => $this->stringValue($frontMatter, 'category'),
                'place' => $this->stringValue($frontMatter, 'place'),
                'realEstateListing' => $this->stringValue($frontMatter, 'realEstateListing'),
                'amenityFeature' => $this->stringList($frontMatter['amenityFeature'] ?? []),
                'tags' => $this->stringList($frontMatter['keywords'] ?? []),
                'associatedMedia' => $this->mediaEdges($frontMatter['associatedMedia'] ?? []),
                'hasPart' => $this->hasPartBlocks($frontMatter['hasPart'] ?? []),
                'body' => $body,
                'metaTitle' => $this->nullableStringValue($frontMatter, 'metaTitle'),
                'metaDescription' => $this->nullableStringValue($frontMatter, 'metaDescription'),
                'highlight' => $this->nullableStringValue($frontMatter, 'highlight'),
                'review' => $this->nullableStringValue($frontMatter, 'review'),
                'offer' => is_array($frontMatter['offer'] ?? null) ? $frontMatter['offer'] : [],
                'fields' => $frontMatter,
                'source' => $filePath,
            ];
        }

        return $accommodations;
    }

    /**
     * @return list<string>
     */
    private function detectWarnings(ProjectSeedData $data): array
    {
        $warnings = [];

        foreach ($data->mediaObjects as $mediaObject) {
            if (null === $mediaObject['caption'] || '' === trim($mediaObject['caption'])) {
                $warnings[] = sprintf('Media "%s" has no caption; filename fallback will be used.', $mediaObject['filename']);
            }
        }

        foreach ($data->ignoredFiles as $file) {
            $warnings[] = sprintf('Ignored seed file outside API V1 scope: %s', $file);
        }

        return $warnings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseYamlItems(string $path): array
    {
        if (!is_file($path)) {
            throw new \UnexpectedValueException(sprintf('Missing YAML file: %s', $path));
        }

        $parsed = Yaml::parseFile($path);
        if (!is_array($parsed) || !is_array($parsed['items'] ?? null)) {
            throw new \UnexpectedValueException(sprintf('YAML file must expose an "items" list: %s', $path));
        }

        return array_values(array_filter($parsed['items'], 'is_array'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function parseMarkdown(string $path): array
    {
        $content = file_get_contents($path);
        if (false === $content) {
            throw new \UnexpectedValueException(sprintf('Unable to read Markdown file: %s', $path));
        }

        if (!preg_match('/\\A---\\R(?P<yaml>.*?)\\R---\\R?(?P<body>.*)\\z/s', $content, $matches)) {
            throw new \UnexpectedValueException(sprintf('Markdown file has no YAML front matter: %s', $path));
        }

        $frontMatter = Yaml::parse($matches['yaml']);
        if (!is_array($frontMatter)) {
            throw new \UnexpectedValueException(sprintf('Invalid YAML front matter: %s', $path));
        }

        return [$frontMatter, trim($matches['body'])];
    }

    /**
     * @return list<string>
     */
    private function markdownFiles(string $path): array
    {
        $finder = (new Finder())->files()->in($path)->name('*.md')->sortByName();
        $files = [];

        foreach ($finder as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function findIgnoredFiles(string $dataPath): array
    {
        $uiPath = $dataPath.'/ui';
        if (!is_dir($uiPath)) {
            return [];
        }

        $files = [];
        foreach ((new Finder())->files()->in($uiPath)->sortByName() as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function stringValue(array $item, string $key): string
    {
        $value = $item[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function nullableStringValue(array $item, string $key): ?string
    {
        $value = $item[$key] ?? null;

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item) && '' !== trim((string) $item)) {
                $items[] = trim((string) $item);
            }
        }

        return $items;
    }

    /**
     * @return list<array{image: string, caption: string|null, keywords: list<string>, representativeOfPage: bool|null}>
     */
    private function mediaEdges(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $edges = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $image = $this->stringValue($item, 'image');
            if ('' === $image) {
                continue;
            }

            $edges[] = [
                'image' => $image,
                'caption' => $this->nullableStringValue($item, 'caption'),
                'keywords' => $this->stringList($item['keywords'] ?? []),
                'representativeOfPage' => isset($item['representativeOfPage']) ? (bool) $item['representativeOfPage'] : null,
            ];
        }

        return $edges;
    }

    /**
     * Parses the `hasPart` content blocks (Schema.org) of an accommodation. Structural
     * fields are shared across locales; name/headline/text are localized source-locale text.
     *
     * @return list<array{additionalType: string, position: int|null, name: string|null, headline: string|null, text: string|null, associatedMedia: list<string>, meta: array<string, mixed>}>
     */
    private function hasPartBlocks(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $blocks = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $additionalType = $this->stringValue($item, 'additionalType');
            if ('' === $additionalType) {
                continue;
            }

            $position = $item['position'] ?? null;

            $blocks[] = [
                'additionalType' => $additionalType,
                'position' => is_numeric($position) ? (int) $position : null,
                'name' => $this->nullableStringValue($item, 'name'),
                'headline' => $this->nullableStringValue($item, 'headline'),
                'text' => $this->nullableStringValue($item, 'text'),
                'associatedMedia' => array_map(
                    static fn (array $edge): string => $edge['image'],
                    $this->mediaEdges($item['associatedMedia'] ?? []),
                ),
                'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : [],
            ];
        }

        return $blocks;
    }

    private function normalizeMediaReference(string $reference): string
    {
        $reference = trim($reference);

        if (str_starts_with($reference, 'media-')) {
            return $reference;
        }

        return ltrim($reference, '/');
    }

    private function isLocalMediaUrl(string $url): bool
    {
        return str_starts_with(trim($url), '/');
    }

    private function identifierFromFilePathOrSlug(string $filePath, string $slug): ?string
    {
        $filename = basename($filePath, '.md');
        foreach ([$filename, $slug] as $candidate) {
            if (preg_match('/(?:^|-)([a-z][a-z0-9]*\d{3,})(?=-|$)/i', $candidate, $matches)) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    private function normalizeCodeSet(string $codeSet): string
    {
        return 'accommodation-category' === $codeSet ? Accommodation::CATEGORY_CODE_SET : $codeSet;
    }
}
