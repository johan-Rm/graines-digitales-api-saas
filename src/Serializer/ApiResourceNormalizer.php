<?php

namespace App\Serializer;

use App\Entity\MediaObject;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\ProjectScopedResource;
use App\Entity\ResourceTranslation;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
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

    public function __construct(
        #[Autowire(service: 'api_platform.jsonld.normalizer.item')]
        private readonly NormalizerInterface $normalizer,
        private readonly StorageInterface $storage,
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
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->compactValue($value, $withTranslations);
        }

        return $data;
    }

    private function compactValue(mixed $value, bool $withTranslations = false): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isResourceObject($value)) {
            return $this->compactResourceObject($value);
        }

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
            return $item;
        }

        if ($this->isResourceObject($item) && !$this->isEmbeddedType($item) && (!$withTranslations || !$this->isTranslationType($item))) {
            return $this->compactResourceObject($item);
        }

        if (!$withTranslations) {
            unset($item['translations']);
        }

        foreach ($item as $key => $value) {
            $item[$key] = $this->compactValue($value, $withTranslations);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isTranslationType(array $value): bool
    {
        $type = $value['@type'] ?? null;

        return is_string($type) && str_ends_with($type, 'Translation');
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isResourceObject(array $value): bool
    {
        return is_string($value['id'] ?? null)
            && (is_string($value['@id'] ?? null) || is_string($value['@type'] ?? null));
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isEmbeddedType(array $value): bool
    {
        $type = $value['@type'] ?? null;

        return is_string($type) && in_array($type, self::EMBEDDED_TYPES, true);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function compactResourceObject(array $value): ?string
    {
        if ('CategoryCode' === ($value['@type'] ?? null)) {
            $code = $value['codeValue'] ?? null;

            return is_string($code) ? $code : null;
        }

        $id = $value['id'] ?? null;

        return is_string($id) ? $id : null;
    }
}
