<?php

namespace App\Seed;

use App\Entity\Accommodation;
use App\Entity\AccommodationComponent;
use App\Entity\AccommodationMedia;
use App\Entity\AccommodationTranslation;
use App\Entity\CategoryCode;
use App\Entity\MediaObject;
use App\Entity\Project;
use App\Entity\ResourceTranslation;
use App\Enum\AccommodationComponentType;
use App\Enum\ContentStatus;
use App\Media\MediaCaptionGuesser;
use App\State\AccommodationIdentifierGenerator;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AccommodationSeedImporter implements ProjectSeedImporterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccommodationIdentifierGenerator $identifierGenerator,
        private ResourceAutoFiller $autoFiller,
        private ResourceAutoTranslator $autoTranslator,
        private MediaObjectSeedCaptionApplier $captionApplier,
        private MediaCaptionGuesser $mediaCaptionGuesser,
    ) {
    }

    public function family(): string
    {
        return ProjectSeedFamily::ACCOMMODATIONS;
    }

    public function requires(ProjectSeedData $data): array
    {
        $requires = [ProjectSeedFamily::METADATA];

        foreach ($data->accommodations as $accommodation) {
            $hasPartMedia = array_filter($accommodation['hasPart'], static fn (array $block): bool => [] !== $block['associatedMedia']);
            if ([] !== $accommodation['associatedMedia'] || [] !== $hasPartMedia) {
                $requires[] = ProjectSeedFamily::MEDIA;
                break;
            }
        }

        return array_values(array_unique($requires));
    }

    public function import(Project $project, ProjectSeedData $data): int
    {
        $count = 0;
        $reservedIdentifiers = [];
        $imported = [];
        $captionedMediaObjects = [];

        foreach ($data->accommodations as $source) {
            $accommodation = $this->findAccommodation($project, $source['identifier'], $source['slug'], $source['locale']);

            if (!$accommodation instanceof Accommodation) {
                $accommodation = (new Accommodation())
                    ->setProject($project);
            }

            $accommodation->setCurrentLocale($source['locale']);
            $accommodation
                ->setCategory($this->categoryCode($project, Accommodation::CATEGORY_CODE_SET, $source['category']))
                ->setPlace($this->categoryCode($project, Accommodation::PLACE_CODE_SET, $source['place']))
                ->setRealEstateListing($this->categoryCode($project, Accommodation::REAL_ESTATE_LISTING_CODE_SET, $source['realEstateListing']))
                ->setName($source['name'])
                ->setLabel($source['name'])
                ->setHighlight($source['highlight'])
                ->setBody($source['body'])
                ->setReview($source['review'])
                ->setMetaTitle($source['metaTitle'])
                ->setMetaDescription($source['metaDescription'])
                ->setOffer($source['offer'])
                ->setStatus(ContentStatus::Published)
                ->setIsActive($this->boolField($source['fields'], 'isActive', true))
                ->setYearBuilt($this->intField($source['fields'], 'yearBuilt'))
                ->setAreaSize($this->stringField($source['fields'], 'areaSize'))
                ->setAreaTerrace($this->stringField($source['fields'], 'areaTerrace'))
                ->setFloorSize($this->stringField($source['fields'], 'floorSize'))
                ->setLandArea($this->stringField($source['fields'], 'landArea'))
                ->setLevel($this->intField($source['fields'], 'level'))
                ->setNumberOfRooms($this->intField($source['fields'], 'numberOfRooms'))
                ->setNumberOfBedrooms($this->intField($source['fields'], 'numberOfBedrooms'))
                ->setNumberOfBathroomsTotal($this->intField($source['fields'], 'numberOfBathroomsTotal'))
                ->setNumberOfGarages($this->intField($source['fields'], 'numberOfGarages'))
                ->setOccupancy($this->intField($source['fields'], 'occupancy'))
                ->clearAmenityFeature()
                ->clearTags()
                ->clearAssociatedMedia()
                ->clearHasPart();

            $accommodation->setIdentifier($this->resolveIdentifier($accommodation, $source['identifier'], $reservedIdentifiers));

            // Slug is never stored from the seed source: it is recomputed server-side
            // from identifier + name + place, exactly like the normal write flow
            // (ProjectScopedPersistProcessor). See docs/04-domain-model/accommodations.md.
            $translation = $accommodation->getTranslation($source['locale']);
            if ($translation instanceof AccommodationTranslation) {
                $translation->recomputeSlug();
            }

            foreach ($source['amenityFeature'] as $amenityFeature) {
                $accommodation->addAmenityFeature($this->categoryCode($project, Accommodation::AMENITY_FEATURE_CODE_SET, $amenityFeature));
            }

            foreach ($source['tags'] as $tag) {
                $accommodation->addTag($this->categoryCode($project, Accommodation::TAG_CODE_SET, $tag));
            }

            foreach ($source['associatedMedia'] as $index => $edge) {
                $mediaObject = $this->findMediaObject($project, $edge['image']);
                if (!$mediaObject instanceof MediaObject) {
                    throw new \RuntimeException(sprintf('Media reference "%s" was not found while importing accommodation "%s".', $edge['image'], $source['slug']));
                }

                $accommodation->addAssociatedMedia(
                    (new AccommodationMedia())
                        ->setMediaObject($mediaObject)
                        ->setPosition($index + 1)
                        ->setKeywords($edge['keywords'])
                        ->setRepresentativeOfPage($edge['representativeOfPage'] ?? null),
                );

                $caption = is_string($edge['caption'] ?? null) && '' !== trim($edge['caption']) ? $edge['caption'] : null;
                // Mirror the upload flow: when no caption is provided, derive one from the
                // filename — but only if the media has none yet for the source locale, so an
                // existing (or media-step) caption is never clobbered by a weaker guess.
                if (null === $caption && null === $mediaObject->getTranslation($source['locale'])?->getCaption()) {
                    $filename = $mediaObject->getFilePath() ?? '';
                    $caption = '' !== $filename
                        ? ($this->mediaCaptionGuesser->guessFromFilename($filename) ?? $filename)
                        : null;
                }
                if (null !== $caption && '' !== $caption) {
                    $this->captionApplier->apply($mediaObject, $source['locale'], $caption);
                    $this->entityManager->persist($mediaObject);
                    $captionedMediaObjects[spl_object_id($mediaObject)] = $mediaObject;
                }
            }

            $this->applyHasPart($accommodation, $project, $source['hasPart'], $source['locale'], $source['slug']);

            $accommodation->ensureDefaultQualities();
            $this->entityManager->persist($accommodation);

            $translation = $accommodation->getTranslation($source['locale']);
            if ($translation instanceof ResourceTranslation) {
                $this->autoFiller->fill($translation);
            }

            $imported[] = $accommodation;
            $count++;
        }

        // Flush source-locale data, then refresh each entity so that Doctrine's
        // collection is fully re-initialized from DB (avoids partial-init from the
        // JOIN used in findAccommodation, which would cause duplicate-translation inserts).
        $this->entityManager->flush();
        foreach ($imported as $accommodation) {
            $this->entityManager->refresh($accommodation);
            $this->autoTranslator->synchronize($accommodation, $project);
        }
        foreach ($captionedMediaObjects as $mediaObject) {
            $this->autoTranslator->synchronize($mediaObject, $project);
        }

        return $count;
    }

    /**
     * Rebuilds the `hasPart` content blocks of an accommodation from the seed source.
     * Structural fields are locale-agnostic; name/headline/text are written on the
     * source-locale translation, mirroring the API write flow (read falls back to the
     * source locale for other locales). Components cascade-persist with the accommodation.
     *
     * @param list<array{additionalType: string, position: int|null, name: string|null, headline: string|null, text: string|null, associatedMedia: list<string>, meta: array<string, mixed>}> $blocks
     */
    private function applyHasPart(Accommodation $accommodation, Project $project, array $blocks, string $locale, string $slug): void
    {
        foreach ($blocks as $index => $block) {
            $component = new AccommodationComponent();
            $component->setAdditionalType($this->componentType($block['additionalType'], $slug));
            $component->setPosition($block['position'] ?? ($index + 1));
            $component->setMeta($block['meta']);
            $component->setAssociatedMedia($this->resolveComponentMedia($project, $block['associatedMedia'], $slug));

            $component->setCurrentLocale($locale);
            $component->setName($block['name']);
            $component->setHeadline($block['headline']);
            $component->setText($block['text']);

            $accommodation->addComponent($component);
        }
    }

    private function componentType(string $raw, string $slug): AccommodationComponentType
    {
        $type = AccommodationComponentType::tryFrom($raw);
        if (!$type instanceof AccommodationComponentType) {
            throw new \RuntimeException(sprintf('Unknown hasPart additionalType "%s" while importing accommodation "%s".', $raw, $slug));
        }

        return $type;
    }

    /**
     * @param list<string> $references
     *
     * @return list<string>
     */
    private function resolveComponentMedia(Project $project, array $references, string $slug): array
    {
        $uuids = [];
        foreach ($references as $reference) {
            $mediaObject = $this->findMediaObject($project, $reference);
            if (!$mediaObject instanceof MediaObject) {
                throw new \RuntimeException(sprintf('Media reference "%s" was not found while importing hasPart of accommodation "%s".', $reference, $slug));
            }

            $uuids[] = (string) $mediaObject->getId();
        }

        return $uuids;
    }

    private function findAccommodation(Project $project, ?string $identifier, string $slug, string $locale): ?Accommodation
    {
        $accommodation = null;
        if (null !== $identifier) {
            $accommodation = $this->entityManager->getRepository(Accommodation::class)->findOneBy([
                'project' => $project,
                'identifier' => $identifier,
            ]);
        }

        if ($accommodation instanceof Accommodation) {
            return $accommodation;
        }

        $result = $this->entityManager->createQueryBuilder()
            ->select('accommodation')
            ->from(Accommodation::class, 'accommodation')
            ->innerJoin(AccommodationTranslation::class, 'translation', 'WITH', 'translation.translatable = accommodation')
            ->where('accommodation.project = :project')
            ->andWhere('translation.project = :project')
            ->andWhere('translation.slug = :slug')
            ->andWhere('translation.locale = :locale')
            ->setParameter('project', $project)
            ->setParameter('slug', $slug)
            ->setParameter('locale', $locale)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Accommodation ? $result : null;
    }

    /**
     * @param array<string, true> $reservedIdentifiers
     */
    private function resolveIdentifier(Accommodation $accommodation, ?string $sourceIdentifier, array &$reservedIdentifiers): string
    {
        if (null !== $sourceIdentifier) {
            $identifier = strtoupper($sourceIdentifier);
            $reservedIdentifiers[$identifier] = true;

            return $identifier;
        }

        if (1 === preg_match('/^[A-Z]+[0-9]{3,}$/', $accommodation->getIdentifier())) {
            $reservedIdentifiers[$accommodation->getIdentifier()] = true;

            return $accommodation->getIdentifier();
        }

        $identifier = $this->identifierGenerator->generate($accommodation, $accommodation);
        while (isset($reservedIdentifiers[$identifier])) {
            $identifier = $this->nextIdentifier($identifier);
        }

        $reservedIdentifiers[$identifier] = true;

        return $identifier;
    }

    private function nextIdentifier(string $identifier): string
    {
        if (1 !== preg_match('/^(?<prefix>[A-Z]+)(?<suffix>[0-9]{3,})$/', $identifier, $matches)) {
            throw new \RuntimeException(sprintf('Cannot increment generated accommodation identifier "%s".', $identifier));
        }

        $suffix = (int) $matches['suffix'] + 1;

        return sprintf('%s%0'.strlen($matches['suffix']).'d', $matches['prefix'], $suffix);
    }

    private function categoryCode(Project $project, string $codeSet, string $code): CategoryCode
    {
        $categoryCode = $this->entityManager->getRepository(CategoryCode::class)->findOneBy([
            'project' => $project,
            'inCodeSet' => $codeSet,
            'codeValue' => $code,
        ]);

        if (!$categoryCode instanceof CategoryCode) {
            throw new \RuntimeException(sprintf('CategoryCode "%s" in code set "%s" was not found.', $code, $codeSet));
        }

        return $categoryCode;
    }

    private function findMediaObject(Project $project, string $reference): ?MediaObject
    {
        $filename = str_starts_with($reference, 'media-') ? substr($reference, 6) : ltrim($reference, '/');
        if (str_starts_with($reference, 'media-') || !str_contains(basename($reference), '.')) {
            $result = $this->entityManager->createQueryBuilder()
                ->select('mediaObject')
                ->from(MediaObject::class, 'mediaObject')
                ->where('mediaObject.project = :project')
                ->andWhere('mediaObject.filePath LIKE :basename')
                ->setParameter('project', $project)
                ->setParameter('basename', $filename.'.%')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $result instanceof MediaObject ? $result : null;
        }

        $mediaObject = $this->entityManager->getRepository(MediaObject::class)->findOneBy([
            'project' => $project,
            'filePath' => $filename,
        ]);

        return $mediaObject instanceof MediaObject ? $mediaObject : null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function intField(array $fields, string $key): ?int
    {
        $value = $fields[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function stringField(array $fields, string $key): ?string
    {
        $value = $fields[$key] ?? null;

        return is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function boolField(array $fields, string $key, bool $default): bool
    {
        $value = $fields[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }
}
