<?php

namespace App\Serializer;

use App\Entity\Accommodation;
use App\Entity\CategoryCode;
use App\Entity\MediaObject;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class AccommodationNormalizer implements NormalizerInterface
{
    private const ALREADY_CALLED = 'ACCOMMODATION_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'api_platform.jsonld.normalizer.item')]
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @return array<string, mixed>|\ArrayObject<string, mixed>|bool|float|int|string|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($object, $format, $context);

        if (!$object instanceof Accommodation || !is_array($data)) {
            return $data;
        }

        unset($data['translations']);

        $data['category'] = $this->idOf($object->getCategory());
        $data['realEstateListing'] = $this->idOf($object->getRealEstateListing());
        $data['place'] = $this->idOf($object->getPlace());
        $data['amenityFeature'] = $this->idsOf($object->getAmenityFeature());
        $data['tags'] = $this->idsOf($object->getTags());
        $data['project'] = $this->idOf($object->getProject());
        $data['createdBy'] = $this->idOf($object->getCreatedBy());
        $data['updatedBy'] = $this->idOf($object->getUpdatedBy());

        if (isset($data['associatedMedia']) && is_array($data['associatedMedia'])) {
            $data['associatedMedia'] = $this->normalizeAssociatedMedia($data['associatedMedia']);
        }

        if (isset($data['qualities']) && is_array($data['qualities'])) {
            $data['qualities'] = $this->removeNestedRelations($data['qualities']);
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Accommodation && !isset($context[self::ALREADY_CALLED]);
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Accommodation::class => true];
    }

    private function idOf(?object $entity): ?string
    {
        if (null === $entity || !method_exists($entity, 'getId')) {
            return null;
        }

        return (string) $entity->getId();
    }

    /**
     * @param Collection<int, CategoryCode> $entities
     *
     * @return list<string>
     */
    private function idsOf(Collection $entities): array
    {
        return array_values(array_filter(array_map(
            fn (object $entity): ?string => $this->idOf($entity),
            $entities->toArray(),
        )));
    }

    /**
     * @param array<int|string, mixed> $items
     *
     * @return array<int|string, mixed>
     */
    private function normalizeAssociatedMedia(array $items): array
    {
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $mediaObject = $item['mediaObject'] ?? null;
            if (is_array($mediaObject)) {
                $item['mediaObject'] = $this->idFromNormalizedItem($mediaObject);
            } elseif ($mediaObject instanceof MediaObject) {
                $item['mediaObject'] = $this->idOf($mediaObject);
            }

            unset($item['translations'], $item['project'], $item['createdBy'], $item['updatedBy']);
            $items[$index] = $item;
        }

        return $items;
    }

    /**
     * @param array<int|string, mixed> $items
     *
     * @return array<int|string, mixed>
     */
    private function removeNestedRelations(array $items): array
    {
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            unset($item['translations'], $item['project'], $item['createdBy'], $item['updatedBy']);
            $items[$index] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function idFromNormalizedItem(array $item): ?string
    {
        $id = $item['id'] ?? null;

        return is_string($id) ? $id : null;
    }
}
