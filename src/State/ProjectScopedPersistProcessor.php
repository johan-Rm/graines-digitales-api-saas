<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Entity\CategoryCode;
use App\Entity\MediaObject;
use App\Entity\Project;
use App\Entity\ProjectScopedResource;
use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;
use App\Media\MediaCaptionGuesser;
use App\Media\MediaUploadFinalizer;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\LocaleResolver;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ProjectScopedPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private AccommodationIdentifierGenerator $accommodationIdentifierGenerator,
        private LocaleResolver $localeResolver,
        private MediaCaptionGuesser $mediaCaptionGuesser,
        private MediaUploadFinalizer $mediaUploadFinalizer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ProjectScopedResource) {
            $projectId = (string) ($uriVariables['projectId'] ?? '');
            $project = $this->projectAccessGuard->getWritableProject($projectId);
            $actor = $this->currentUserProvider->getUser();

            $isCreate = null === $data->getProject();

            if ($isCreate) {
                $data->setProject($project);
                $data->markCreatedBy($actor);
            }

            // For creates, the provider is not called (read: false), so locale is not resolved.
            // Resolve it here from ?locale query param, falling back to the project source locale.
            if ($isCreate && $data instanceof TranslatableResource) {
                $initialLocale = $data->getCurrentLocale();
                $initialTranslation = $data->getTranslation($initialLocale);
                $locale = $this->localeResolver->resolve($project);
                $data->setResolvedLocale($locale, $locale, null);

                if ($initialLocale !== $locale && $initialTranslation instanceof ResourceTranslation && null === $data->getTranslation($locale)) {
                    $initialTranslation->setLocale($locale);
                }
            }

            if ($data instanceof Accommodation) {
                $this->validateAccommodationCategoryCodes($data, $project);

                $currentIdentifier = $data->getIdentifier();
                if ('' === trim($currentIdentifier) || !$this->accommodationIdentifierGenerator->isConsistentWith($data)) {
                    $excludedFromCount = '' !== trim($currentIdentifier) ? $data : null;
                    $data->setIdentifier($this->accommodationIdentifierGenerator->generate($data, $excludedFromCount));
                }
                $data->ensureDefaultQualities();
            }

            // Must run before the TranslatableResource block so that the auto-filled caption
            // creates the translation before we look it up and explicitly persist it below.
            if ($data instanceof MediaObject && null !== ($file = $data->getFile())) {
                $checksum = hash_file('sha256', $file->getPathname());
                $data->setChecksumSha256(false === $checksum ? null : $checksum);

                if ($file instanceof UploadedFile && (null === $data->getCaption() || '' === trim($data->getCaption()))) {
                    $data->setCaption($this->mediaCaptionGuesser->guessFromFilename($file->getClientOriginalName()));
                }
            }

            $data->touch($actor);
            $this->entityManager->persist($data);

            if ($data instanceof TranslatableResource) {
                $translation = $data->getTranslation($data->getCurrentLocale());
                if ($translation instanceof ResourceTranslation) {
                    $translation->setTranslatable($data);
                    if ($translation instanceof AccommodationTranslation) {
                        $translation->recomputeSlug();
                    }
                    $translation->touch($actor);
                    $this->entityManager->persist($translation);
                }
            }
        }

        try {
            $result = $this->decorated->process($data, $operation, $uriVariables, $context);
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException('A resource with the same unique identifier already exists.', $e);
        }

        if ($data instanceof MediaObject) {
            $this->mediaUploadFinalizer->finalize($data);
        }

        return $result;
    }

    private function validateAccommodationCategoryCodes(Accommodation $accommodation, Project $project): void
    {
        if (!$accommodation->hasCategory() || !$accommodation->hasRealEstateListing() || !$accommodation->hasPlace()) {
            throw new BadRequestHttpException('Accommodation requires category, realEstateListing and place.');
        }

        $this->validateRequiredCategoryCode($accommodation->getCategory(), $project, Accommodation::CATEGORY_CODE_SET, 'category');
        $this->validateRequiredCategoryCode($accommodation->getRealEstateListing(), $project, Accommodation::REAL_ESTATE_LISTING_CODE_SET, 'realEstateListing');
        $this->validateRequiredCategoryCode($accommodation->getPlace(), $project, Accommodation::PLACE_CODE_SET, 'place');

        foreach ($accommodation->getAmenityFeature() as $amenityFeature) {
            if ($amenityFeature instanceof CategoryCode) {
                $this->validateRequiredCategoryCode($amenityFeature, $project, Accommodation::AMENITY_FEATURE_CODE_SET, 'amenityFeature');
            }
        }

        foreach ($accommodation->getTags() as $tag) {
            if ($tag instanceof CategoryCode) {
                $this->validateRequiredCategoryCode($tag, $project, Accommodation::TAG_CODE_SET, 'tags');
            }
        }
    }

    private function validateRequiredCategoryCode(CategoryCode $categoryCode, Project $project, string $expectedCodeSet, string $field): void
    {
        if ($expectedCodeSet !== $categoryCode->getInCodeSet()) {
            throw new BadRequestHttpException(sprintf('Accommodation.%s must reference a CategoryCode from "%s".', $field, $expectedCodeSet));
        }

        if ((string) $categoryCode->getProject()?->getId() !== (string) $project->getId()) {
            throw new BadRequestHttpException(sprintf('Accommodation.%s must reference a CategoryCode from the current project.', $field));
        }
    }
}
