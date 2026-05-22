<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Accommodation;
use App\Entity\CategoryCode;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ContentStatus;
use App\Security\CurrentUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class DashboardAccommodationByIdentifierController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
    ) {
    }

    #[Route('/api/dashboard/accommodations/{identifier}', name: 'api_dashboard_accommodation_put_by_identifier', methods: ['PUT'])]
    public function __invoke(Request $request, string $identifier): Response
    {
        $accommodation = $this->findWritableAccommodation($identifier);
        $project = $accommodation->getProject();

        if (null === $project) {
            throw new NotFoundHttpException('Accommodation not found.');
        }

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        $locale = (string) $request->query->get('locale', $project->getSourceLocale());
        $accommodation->setCurrentLocale($locale);
        $this->applyPayload($accommodation, $project, $payload);
        $accommodation->touch($this->currentUserProvider->getUser());

        foreach ($accommodation->getTranslations() as $translation) {
            $translation->setTranslatable($accommodation);
            $translation->touch($this->currentUserProvider->getUser());
            $this->entityManager->persist($translation);
        }

        $this->entityManager->persist($accommodation);
        $this->entityManager->flush();

        return new JsonResponse([
            '@id' => sprintf('/api/accommodations/%s', $accommodation->getId()),
            'id' => (string) $accommodation->getId(),
            'identifier' => $accommodation->getIdentifier(),
            'slug' => $accommodation->getSlug(),
            'name' => $accommodation->getName(),
            'locale' => $locale,
            'isActive' => $accommodation->isActive(),
            'areaSize' => $accommodation->getAreaSize(),
            'floorSize' => $accommodation->getFloorSize(),
            'offerPrice' => $accommodation->getOfferPrice(),
            'offerPriceCurrency' => $accommodation->getOfferPriceCurrency(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(Accommodation $accommodation, Project $project, array $payload): void
    {
        if (isset($payload['identifier']) && is_string($payload['identifier'])) {
            $accommodation->setIdentifier($payload['identifier']);
        }

        foreach (['slug', 'name', 'label', 'highlight', 'body', 'review', 'metaTitle', 'metaDescription'] as $field) {
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

        $this->applyNullableInt($accommodation, $payload, 'yearBuilt');
        $this->applyNullableInt($accommodation, $payload, 'areaSize');
        $this->applyNullableInt($accommodation, $payload, 'areaTerrace');
        $this->applyNullableInt($accommodation, $payload, 'numberOfRooms');
        $this->applyNullableInt($accommodation, $payload, 'numberOfBedrooms');
        $this->applyNullableInt($accommodation, $payload, 'numberOfBathroomsTotal');
        $this->applyNullableInt($accommodation, $payload, 'numberOfGarages');
        $this->applyNullableInt($accommodation, $payload, 'occupancy');
        $this->applyNullableString($accommodation, $payload, 'floorSize');
        $this->applyNullableString($accommodation, $payload, 'landArea');
        $this->applyNullableString($accommodation, $payload, 'offerPrice');
        $this->applyNullableString($accommodation, $payload, 'offerPriceCurrency');
        $this->applyNullableString($accommodation, $payload, 'offerPriceSpecification');
        $this->applyNullableString($accommodation, $payload, 'offerAvailability');
        $this->applyNullableString($accommodation, $payload, 'realEstateAgentIdentifier');
        $this->applyNullableString($accommodation, $payload, 'realEstateAgentName');
        $this->applyNullableString($accommodation, $payload, 'realEstateAgentPhone');
        $this->applyNullableString($accommodation, $payload, 'realEstateAgentEmail');

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
        $categoryCode = $this->entityManager->getRepository(CategoryCode::class)->findOneBy([
            'id' => $id,
            'project' => $project,
            'inCodeSet' => $expectedCodeSet,
        ]);

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

    private function findWritableAccommodation(string $identifier): Accommodation
    {
        $user = $this->currentUserProvider->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        $accommodations = $this->entityManager->getRepository(Accommodation::class)->findBy([
            'identifier' => $identifier,
            'deletedAt' => null,
        ]);

        $writableAccommodations = [];
        foreach ($accommodations as $accommodation) {
            if (!$accommodation instanceof Accommodation || null === $accommodation->getProject()) {
                continue;
            }

            $membership = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
                'project' => $accommodation->getProject(),
                'user' => $user,
            ]);

            if ($membership instanceof ProjectMember && $membership->getRole()->canWrite()) {
                $writableAccommodations[] = $accommodation;
            }
        }

        if ([] === $writableAccommodations) {
            throw new NotFoundHttpException('Accommodation not found.');
        }

        if (count($writableAccommodations) > 1) {
            throw new ConflictHttpException('Accommodation identifier matches multiple writable projects.');
        }

        return $writableAccommodations[0];
    }
}
