<?php

declare(strict_types=1);

namespace App\State;

use App\Entity\Accommodation;
use App\Entity\CategoryCode;
use App\Entity\Project;
use App\Entity\PropertyValue;
use App\Enum\AccommodationQuality;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;
use ValueError;

final readonly class AccommodationStructuralPayloadApplier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccommodationAssociatedMediaPayloadApplier $associatedMediaApplier,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function apply(Accommodation $accommodation, Project $project, array $payload): void
    {
        $this->applyTranslatedFields($accommodation, $payload);
        $this->applyCategoryCodes($accommodation, $project, $payload);
        $this->applyAmenityFeature($accommodation, $project, $payload);
        $this->applyTags($accommodation, $project, $payload);
        $this->associatedMediaApplier->apply($accommodation, $project, $payload, $accommodation->getCurrentLocale());
        $this->applyQualities($accommodation, $payload);
        $this->applyScalarFields($accommodation, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyTranslatedFields(Accommodation $accommodation, array $payload): void
    {
        if (isset($payload['slug']) && is_string($payload['slug'])) {
            $accommodation->setSlug($payload['slug']);
        }

        foreach (['name', 'label', 'highlight', 'body', 'review', 'metaTitle', 'metaDescription'] as $field) {
            if (array_key_exists($field, $payload)) {
                $setter = 'set'.ucfirst($field);
                $accommodation->{$setter}(is_string($payload[$field]) ? $payload[$field] : null);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyCategoryCodes(Accommodation $accommodation, Project $project, array $payload): void
    {
        if (isset($payload['category']) && is_string($payload['category'])) {
            $accommodation->setCategory($this->resolveCategoryCode($payload['category'], $project, 'accommodation-type'));
        }

        if (isset($payload['realEstateListing']) && is_string($payload['realEstateListing'])) {
            $accommodation->setRealEstateListing($this->resolveCategoryCode($payload['realEstateListing'], $project, 'real-estate-listing'));
        }

        if (isset($payload['place']) && is_string($payload['place'])) {
            $accommodation->setPlace($this->resolveCategoryCode($payload['place'], $project, 'accommodation-place'));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyAmenityFeature(Accommodation $accommodation, Project $project, array $payload): void
    {
        if (!array_key_exists('amenityFeature', $payload)) {
            return;
        }

        if (!is_array($payload['amenityFeature'])) {
            throw new BadRequestHttpException('amenityFeature must be an array.');
        }

        $accommodation->clearAmenityFeature();
        foreach ($payload['amenityFeature'] as $iri) {
            if (!is_string($iri)) {
                throw new BadRequestHttpException('amenityFeature must contain only IRIs.');
            }
            $accommodation->addAmenityFeature($this->resolveCategoryCode($iri, $project, 'amenity-feature'));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyTags(Accommodation $accommodation, Project $project, array $payload): void
    {
        if (!array_key_exists('tags', $payload)) {
            return;
        }

        if (!is_array($payload['tags'])) {
            throw new BadRequestHttpException('tags must be an array.');
        }

        $accommodation->clearTags();
        foreach ($payload['tags'] as $iri) {
            if (!is_string($iri)) {
                throw new BadRequestHttpException('tags must contain only IRIs.');
            }
            $accommodation->addTag($this->resolveCategoryCode($iri, $project, 'tag'));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyQualities(Accommodation $accommodation, array $payload): void
    {
        if (!array_key_exists('qualities', $payload)) {
            $accommodation->ensureDefaultQualities();

            return;
        }

        if (!is_array($payload['qualities'])) {
            throw new BadRequestHttpException('qualities must be an array.');
        }

        $accommodation->clearQualities();
        foreach ($payload['qualities'] as $quality) {
            if (!is_array($quality)) {
                throw new BadRequestHttpException('qualities must contain only objects.');
            }

            $name = $quality['name'] ?? null;
            $value = $quality['value'] ?? null;

            if (!is_string($name) || (!is_int($value) && !is_float($value) && !is_string($value))) {
                throw new BadRequestHttpException('qualities items must contain name and value.');
            }

            try {
                $qualityName = AccommodationQuality::from($name);
            } catch (ValueError) {
                throw new BadRequestHttpException('Unknown quality name.');
            }

            $accommodation->addQuality((new PropertyValue())->setName($qualityName)->setValue((int) $value));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyScalarFields(Accommodation $accommodation, array $payload): void
    {
        $this->applyNullableInt($accommodation, $payload, 'yearBuilt');
        $this->applyNullableString($accommodation, $payload, 'areaSize');
        $this->applyNullableString($accommodation, $payload, 'areaTerrace');
        $this->applyNullableInt($accommodation, $payload, 'numberOfRooms');
        $this->applyNullableInt($accommodation, $payload, 'numberOfBedrooms');
        $this->applyNullableInt($accommodation, $payload, 'numberOfBathroomsTotal');
        $this->applyNullableInt($accommodation, $payload, 'numberOfGarages');
        $this->applyNullableInt($accommodation, $payload, 'occupancy');
        $this->applyNullableString($accommodation, $payload, 'floorSize');
        $this->applyNullableString($accommodation, $payload, 'landArea');

        if (array_key_exists('offer', $payload)) {
            if (!is_array($payload['offer'])) {
                throw new BadRequestHttpException('offer must be an object.');
            }
            $accommodation->setOffer($payload['offer']);
        }

        if (array_key_exists('level', $payload)) {
            $accommodation->setLevel(is_bool($payload['level']) ? $payload['level'] : null);
        }

        if (array_key_exists('isActive', $payload)) {
            $accommodation->setIsActive((bool) $payload['isActive']);
        }

        if (isset($payload['status']) && is_string($payload['status'])) {
            $accommodation->setStatus(\App\Enum\ContentStatus::from($payload['status']));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyNullableInt(Accommodation $accommodation, array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $setter = 'set'.ucfirst($field);
        $value = $payload[$field];
        $intValue = is_numeric($value) ? (int) $value : null;
        $accommodation->{$setter}(is_int($value) ? $value : $intValue);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyNullableString(Accommodation $accommodation, array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $setter = 'set'.ucfirst($field);
        $accommodation->{$setter}(is_scalar($payload[$field]) ? (string) $payload[$field] : null);
    }

    private function resolveCategoryCode(string $iri, Project $project, string $expectedCodeSet): CategoryCode
    {
        $id = basename($iri);
        $criteria = ['project' => $project, 'inCodeSet' => $expectedCodeSet];
        $criteria[Uuid::isValid($id) ? 'id' : 'codeValue'] = $id;

        $categoryCode = $this->entityManager->getRepository(CategoryCode::class)->findOneBy($criteria);

        if (!$categoryCode instanceof CategoryCode) {
            throw new BadRequestHttpException(sprintf('Unknown %s category code IRI.', $expectedCodeSet));
        }

        return $categoryCode;
    }
}
