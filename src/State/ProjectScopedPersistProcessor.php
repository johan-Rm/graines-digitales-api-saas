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
use App\Message\WatermarkMediaObject;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ProjectScopedPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private AccommodationIdentifierGenerator $accommodationIdentifierGenerator,
        #[Autowire(env: 'WEBAPPS_DIR')]
        private string $webappsDir,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ProjectScopedResource) {
            $projectId = (string) ($uriVariables['projectId'] ?? '');
            $project = $this->projectAccessGuard->getWritableProject($projectId);
            $actor = $this->currentUserProvider->getUser();

            if (null === $data->getProject()) {
                $data->setProject($project);
                $data->markCreatedBy($actor);
            }

            if ($data instanceof Accommodation) {
                $this->validateAccommodationCategoryCodes($data, $project);

                if ('' === trim($data->getIdentifier())) {
                    $data->setIdentifier($this->accommodationIdentifierGenerator->generate($data));
                }
                $data->ensureDefaultQualities();
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

            if ($data instanceof MediaObject && null !== $data->getFile()) {
                $data->setChecksumSha256(hash_file('sha256', $data->getFile()->getPathname()) ?: null);
            }
        }

        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        if ($data instanceof MediaObject && null !== $data->getFilePath()) {
            $this->copyToOriginals($data);
            $this->messageBus->dispatch(new WatermarkMediaObject((string) $data->getId()));
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

    private function copyToOriginals(MediaObject $mediaObject): void
    {
        $mediaPath = $mediaObject->getProject()?->getMediaPath();
        if (null === $mediaPath) {
            return;
        }

        $webappsDir = rtrim($this->webappsDir, '/');
        $source = $webappsDir.'/'.$mediaPath.'/'.$mediaObject->getFilePath();
        $originalsDir = $webappsDir.'/'.$mediaPath.'/originals';

        if (!file_exists($source)) {
            return;
        }

        if (!is_dir($originalsDir)) {
            mkdir($originalsDir, 0777, true);
        }

        copy($source, $originalsDir.'/'.$mediaObject->getFilePath());
    }
}
