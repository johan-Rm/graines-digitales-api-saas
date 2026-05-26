<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Entity\CategoryCode;
use App\Entity\PropertyValue;
use App\Entity\Project;
use App\Enum\AccommodationQuality;
use App\Enum\ContentStatus;
use ValueError;
use Symfony\Component\Uid\Uuid;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\AccommodationAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class DashboardAccommodationByIdentifierController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
        private ProjectAccessGuard $projectAccessGuard,
        private AccommodationAutoTranslator $autoTranslator,
    ) {
    }

    #[Route(
        '/api/projects/{projectId}/accommodations/{identifier}',
        name: 'api_project_accommodation_put_by_identifier',
        requirements: ['identifier' => '(?![0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$)[A-Za-z0-9][A-Za-z0-9_-]*'],
        methods: ['PUT'],
        priority: 10
    )]
    public function __invoke(Request $request, string $projectId, string $identifier): Response
    {
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $accommodation = $this->findProjectAccommodation($project, $identifier);

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        $locale = (string) $request->query->get('locale', $project->getSourceLocale());
        $accommodation->setCurrentLocale($locale);
        $this->applyPayload($accommodation, $project, $payload);
        $this->autoTranslator->synchronize($accommodation, $project, $locale, $payload);
        $accommodation->touch($this->currentUserProvider->getUser());

        foreach ($accommodation->getTranslations() as $translation) {
            if (!$translation instanceof AccommodationTranslation) {
                continue;
            }
            $translation->setTranslatable($accommodation);
            if (!isset($payload['slug']) || $translation->getLocale() !== $locale) {
                $translation->recomputeSlug();
            }
            $translation->touch($this->currentUserProvider->getUser());
            $this->entityManager->persist($translation);
        }

        $this->entityManager->persist($accommodation);
        $this->entityManager->flush();

        return new JsonResponse([
            '@id' => sprintf('/api/projects/%s/accommodations/%s', $project->getId(), $accommodation->getId()),
            'id' => (string) $accommodation->getId(),
            'identifier' => $accommodation->getIdentifier(),
            'slug' => $accommodation->getSlug(),
            'name' => $accommodation->getName(),
            'locale' => $locale,
            'isActive' => $accommodation->isActive(),
            'areaSize' => $accommodation->getAreaSize(),
            'areaTerrace' => $accommodation->getAreaTerrace(),
            'floorSize' => $accommodation->getFloorSize(),
            'landArea' => $accommodation->getLandArea(),
            'offer' => $accommodation->getOffer(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(Accommodation $accommodation, Project $project, array $payload): void
    {
        if (isset($payload['slug']) && is_string($payload['slug'])) {
            $accommodation->setSlug($payload['slug']);
        }

        foreach (['name', 'label', 'highlight', 'body', 'review', 'metaTitle', 'metaDescription', 'locationDescription'] as $field) {
            if (array_key_exists($field, $payload)) {
                $setter = 'set'.ucfirst($field);
                $accommodation->{$setter}(is_string($payload[$field]) ? $payload[$field] : null);
            }
        }

        if (isset($payload['category']) && is_string($payload['category'])) {
            $accommodation->setCategory($this->resolveCategoryCode($payload['category'], $project, 'accommodation-type'));
        }

        if (isset($payload['realEstateListing']) && is_string($payload['realEstateListing'])) {
            $accommodation->setRealEstateListing($this->resolveCategoryCode($payload['realEstateListing'], $project, 'real-estate-listing'));
        }

        if (isset($payload['place']) && is_string($payload['place'])) {
            $accommodation->setPlace($this->resolveCategoryCode($payload['place'], $project, 'accommodation-place'));
        }

        if (array_key_exists('amenityFeature', $payload)) {
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

        if (array_key_exists('tags', $payload)) {
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

        if (array_key_exists('qualities', $payload)) {
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
        } else {
            $accommodation->ensureDefaultQualities();
        }

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
            $accommodation->setStatus(ContentStatus::from($payload['status']));
        }
    }

    private function resolveCategoryCode(string $iri, Project $project, string $expectedCodeSet): CategoryCode
    {
        $id = basename($iri);
        $criteria = ['project' => $project, 'inCodeSet' => $expectedCodeSet];
        $criteria[Uuid::isValid($id) ? 'id' : 'code'] = $id;

        $categoryCode = $this->entityManager->getRepository(CategoryCode::class)->findOneBy($criteria);

        if (!$categoryCode instanceof CategoryCode) {
            throw new BadRequestHttpException(sprintf('Unknown %s category code IRI.', $expectedCodeSet));
        }

        return $categoryCode;
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
        $accommodation->{$setter}(is_int($value) ? $value : (is_numeric($value) ? (int) $value : null));
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

    private function findProjectAccommodation(Project $project, string $identifier): Accommodation
    {
        $accommodation = $this->entityManager->getRepository(Accommodation::class)->findOneBy([
            'project' => $project,
            'identifier' => $identifier,
            'deletedAt' => null,
        ]);

        if (!$accommodation instanceof Accommodation) {
            throw new NotFoundHttpException('Accommodation not found.');
        }

        return $accommodation;
    }
}
