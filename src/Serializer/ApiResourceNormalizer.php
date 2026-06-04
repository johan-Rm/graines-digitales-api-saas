<?php

namespace App\Serializer;

use App\Entity\CategoryCode;
use App\Entity\MediaObject;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\ProjectScopedResource;
use App\Entity\ResourceTranslation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\Uuid;
use Vich\UploaderBundle\Storage\StorageInterface;

final class ApiResourceNormalizer implements NormalizerInterface
{
    private const ALREADY_CALLED = 'API_RESOURCE_NORMALIZER_ALREADY_CALLED';

    /**
     * Embedded relation objects carry useful edge/value data. Their own nested relations
     * are still compacted to UUIDs.
     */
    private const EMBEDDED_TYPES = [
        'AccommodationMedia',
        'PropertyValue',
        'WebPageMedia',
    ];

    /**
     * Locale-resolution metadata flattened at the root of a single-locale view.
     * Meaningless on a "with translations" view that already lists every locale.
     */
    private const LOCALE_RESOLUTION_FIELDS = ['locale', 'requestedLocale', 'fallback', 'fallbackLocale'];

    public function __construct(
        #[Autowire(service: 'api_platform.jsonld.normalizer.item')]
        private readonly NormalizerInterface $normalizer,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>|\ArrayObject<string, mixed>|bool|float|int|string|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        if ($object instanceof MediaObject) {
            $project = $object->getProject();
            $mediaBaseUrl = $project?->getMediaBaseUrl();
            $filePath = $object->getFilePath();

            if ($mediaBaseUrl && $filePath) {
                $object->contentUrl = rtrim($mediaBaseUrl, '/').'/'.ltrim($filePath, '/');
            } else {
                $object->contentUrl = $this->storage->resolveUri($object, 'file');
            }
        }

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data)) {
            return $data;
        }

        $withTranslations = in_array('read:with-translations', $context['groups'] ?? [], true);
        if ($object instanceof CategoryCode) {
            $data = $this->addCategoryCodeTextFields($object, $data, $withTranslations);
        }

        return $this->compactRoot($data, $withTranslations);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->isApiResource($data) && !isset($context[self::ALREADY_CALLED]);
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            ProjectScopedResource::class => true,
            ResourceTranslation::class => true,
            Project::class => true,
            Organization::class => true,
            User::class => true,
        ];
    }

    private function isApiResource(mixed $data): bool
    {
        return $data instanceof ProjectScopedResource
            || $data instanceof ResourceTranslation
            || $data instanceof Project
            || $data instanceof Organization
            || $data instanceof User;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function compactRoot(array $data, bool $withTranslations = false): array
    {
        if (!$withTranslations) {
            unset($data['translations']);
        } else {
            $data = $this->stripLocalizedRootFields($data);
        }

        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, '@')) {
                continue;
            }
            $data[$key] = $this->compactValue($value, $withTranslations);
        }

        return $data;
    }

    /**
     * On a "with translations" view the full per-locale collection is returned, so the
     * single-locale fields flattened at the root (slug, name, body, ...) and the locale
     * resolution metadata become redundant and misleading. Drop every root key that is
     * per-locale — i.e. present inside the translation items — plus the resolution metadata.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function stripLocalizedRootFields(array $data): array
    {
        $localizedKeys = array_fill_keys(self::LOCALE_RESOLUTION_FIELDS, true);

        $translations = $data['translations'] ?? null;
        if (is_array($translations)) {
            foreach ($translations as $translation) {
                if (!is_array($translation)) {
                    continue;
                }

                foreach (array_keys($translation) as $key) {
                    $key = (string) $key;
                    if ('id' === $key || str_starts_with($key, '@')) {
                        continue;
                    }
                    $localizedKeys[$key] = true;
                }
            }
        }

        foreach (array_keys($localizedKeys) as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function addCategoryCodeTextFields(CategoryCode $categoryCode, array $data, bool $withTranslations): array
    {
        $data['text'] = $categoryCode->getText();

        if (!$withTranslations || !is_array($data['translations'] ?? null)) {
            return $data;
        }

        $textsByLocale = [];
        foreach ($categoryCode->getTranslations() as $translation) {
            if (method_exists($translation, 'getText')) {
                $textsByLocale[$translation->getLocale()] = $translation->getText();
            }
        }

        $data['translations'] = array_map(
            static function (mixed $translation) use ($textsByLocale): mixed {
                if (!is_array($translation)) {
                    return $translation;
                }

                $locale = $translation['locale'] ?? null;
                $translation['text'] = is_string($locale) ? ($textsByLocale[$locale] ?? null) : null;

                return $translation;
            },
            $data['translations']
        );

        return $data;
    }

    private function compactValue(mixed $value, bool $withTranslations = false): mixed
    {
        if (!is_array($value)) {
            return is_string($value) && str_starts_with($value, '/api/') ? $this->compactIri($value) : $value;
        }

        if ($this->isResourceObject($value)) {
            return $this->compactResourceObject($value);
        }

        return $this->compactArrayValue($value, $withTranslations);
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>|list<mixed>
     */
    private function compactArrayValue(array $value, bool $withTranslations): array
    {
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->compactListItem($item, $withTranslations), $value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->compactValue($item, $withTranslations);
        }

        return $value;
    }

    private function compactListItem(mixed $item, bool $withTranslations = false): mixed
    {
        if (!is_array($item)) {
            return is_string($item) && str_starts_with($item, '/api/') ? $this->compactIri($item) : $item;
        }

        if ($this->isResourceObject($item) && !$this->isEmbeddedType($item) && (!$withTranslations || !$this->isTranslationType($item))) {
            return $this->compactResourceObject($item);
        }

        if (!$withTranslations) {
            unset($item['translations']);
        }

        $isEmbedded = $this->isResourceObject($item) && $this->isEmbeddedType($item);

        foreach ($item as $key => $value) {
            if (str_starts_with((string) $key, '@') || ($isEmbedded && 'id' === $key)) {
                unset($item[$key]);
                continue;
            }
            $item[$key] = $this->compactValue($value, $withTranslations);
        }

        return $item;
    }

    /**
     * Compacts an IRI string to its canonical compact form:
     * - CategoryCode IRI → codeValue (resolved from identity map, no extra query)
     * - Any other resource IRI → UUID (last path segment)
     */
    private function compactIri(string $iri): ?string
    {
        $lastSegment = basename($iri);

        if (!Uuid::isValid($lastSegment)) {
            return $lastSegment;
        }

        if (str_contains($iri, '/category-codes/')) {
            $entity = $this->entityManager->find(CategoryCode::class, Uuid::fromString($lastSegment));
            if ($entity instanceof CategoryCode) {
                return $entity->getCodeValue();
            }
        }

        return $lastSegment;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isTranslationType(array $value): bool
    {
        $type = $value['@type'] ?? null;
        if (is_string($type)) {
            return str_ends_with($type, 'Translation');
        }

        // Shape-based detection when @type is absent (non-JSON-LD serialization context):
        // translation objects have locale + id but no inCodeSet or position
        return array_key_exists('locale', $value)
            && array_key_exists('id', $value)
            && !array_key_exists('inCodeSet', $value)
            && !array_key_exists('position', $value);
    }

    /**
     * Detects a resource object either via JSON-LD annotations (@id / @type) or
     * by the presence of a UUID id field when those annotations are absent (nested objects
     * in a non-JSON-LD serialization context).
     *
     * @param array<string, mixed> $value
     */
    private function isResourceObject(array $value): bool
    {
        if (!is_string($value['id'] ?? null)) {
            return false;
        }

        return is_string($value['@id'] ?? null)
            || is_string($value['@type'] ?? null)
            || Uuid::isValid($value['id']);
    }

    /**
     * Detects embedded types either via @type or by object shape when @type is absent.
     *
     * @param array<string, mixed> $value
     */
    private function isEmbeddedType(array $value): bool
    {
        $type = $value['@type'] ?? null;
        if (is_string($type)) {
            return in_array($type, self::EMBEDDED_TYPES, true);
        }

        // PropertyValue: name + value fields, no inCodeSet
        if (array_key_exists('name', $value) && array_key_exists('value', $value) && !array_key_exists('inCodeSet', $value)) {
            return true;
        }

        // AccommodationMedia: mediaObject + position, no standalone @type
        return array_key_exists('mediaObject', $value) && array_key_exists('position', $value);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function compactResourceObject(array $value): ?string
    {
        // With JSON-LD @type annotation
        if ('CategoryCode' === ($value['@type'] ?? null)) {
            $code = $value['codeValue'] ?? null;

            return is_string($code) ? $code : null;
        }

        // Without @type: detect CategoryCode by shape (has inCodeSet + codeValue)
        if (array_key_exists('inCodeSet', $value) && array_key_exists('codeValue', $value)) {
            $code = $value['codeValue'] ?? null;

            return is_string($code) ? $code : null;
        }

        $id = $value['id'] ?? null;

        return is_string($id) ? $id : null;
    }
}
