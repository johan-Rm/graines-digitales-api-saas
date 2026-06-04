<?php

declare(strict_types=1);

namespace App\State;

use App\Entity\Accommodation;
use App\Entity\AccommodationMedia;
use App\Entity\MediaObject;
use App\Entity\MediaObjectTranslation;
use App\Entity\Project;
use App\Security\CurrentUserProvider;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class AccommodationAssociatedMediaPayloadApplier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function apply(Accommodation $accommodation, Project $project, array $payload, ?string $currentLocale = null): void
    {
        if (!array_key_exists('associatedMedia', $payload)) {
            return;
        }

        if (!is_array($payload['associatedMedia'])) {
            throw new BadRequestHttpException('associatedMedia must be an array.');
        }

        foreach ($accommodation->getAssociatedMedia() as $media) {
            if ($this->entityManager->contains($media)) {
                $this->entityManager->remove($media);
            }
        }
        $accommodation->clearAssociatedMedia();

        /** @var array<string, MediaObject> $affectedMediaObjects */
        $affectedMediaObjects = [];

        foreach ($payload['associatedMedia'] as $item) {
            if (!is_array($item)) {
                throw new BadRequestHttpException('Each associatedMedia item must be an object.');
            }

            $item = $this->normalizeTranslations($item, $currentLocale);

            $mediaObject = $this->resolveMediaObject($item, $project);
            $accommodation->addAssociatedMedia($this->buildAccommodationMedia($item, $mediaObject));

            if (!empty($item['translations']) && is_array($item['translations'])) {
                $id = (string) $mediaObject->getId();
                $affectedMediaObjects[$id] = $mediaObject;
                $this->applyMediaTranslations($mediaObject, $item['translations']);
            }
        }

        if ([] === $affectedMediaObjects) {
            return;
        }

        $actor = $this->currentUserProvider->getUser();

        foreach ($affectedMediaObjects as $mediaObject) {
            $this->autoTranslator->synchronize($mediaObject, $project);
            foreach ($mediaObject->getTranslations() as $translation) {
                if (!$translation instanceof MediaObjectTranslation) {
                    continue;
                }
                $translation->setTranslatable($mediaObject);
                $this->autoFiller->fill($translation);
                $translation->touch($actor);
                $this->entityManager->persist($translation);
            }
            $mediaObject->touch($actor);
            $this->entityManager->persist($mediaObject);
        }
    }

    /**
     * When caption is provided without translations (locale-aware routes), convert it
     * to the translations array format so the rest of the pipeline is uniform.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function normalizeTranslations(array $item, ?string $currentLocale): array
    {
        if (!array_key_exists('caption', $item) || array_key_exists('translations', $item) || null === $currentLocale) {
            return $item;
        }

        $caption = $item['caption'];
        $item['translations'] = [['locale' => $currentLocale, 'caption' => $caption]];

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveMediaObject(array $item, Project $project): MediaObject
    {
        $raw = $item['mediaObject'] ?? null;
        if (!is_string($raw)) {
            throw new BadRequestHttpException('associatedMedia[].mediaObject must be a string.');
        }

        $id = basename($raw);
        if (!Uuid::isValid($id)) {
            throw new BadRequestHttpException('associatedMedia[].mediaObject must be a valid UUID or IRI.');
        }

        $mediaObject = $this->entityManager->find(MediaObject::class, Uuid::fromString($id));
        if (!$mediaObject instanceof MediaObject) {
            throw new BadRequestHttpException('associatedMedia[].mediaObject not found.');
        }

        if ((string) $mediaObject->getProject()?->getId() !== (string) $project->getId()) {
            throw new BadRequestHttpException('associatedMedia[].mediaObject does not belong to this project.');
        }

        return $mediaObject;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildAccommodationMedia(array $item, MediaObject $mediaObject): AccommodationMedia
    {
        $media = new AccommodationMedia();
        $media->setMediaObject($mediaObject);

        if (isset($item['position']) && is_numeric($item['position'])) {
            $media->setPosition((int) $item['position']);
        }

        if (array_key_exists('keywords', $item) && is_array($item['keywords'])) {
            $media->setKeywords(array_values(array_filter($item['keywords'], 'is_string')));
        }

        if (array_key_exists('representativeOfPage', $item)) {
            $media->setRepresentativeOfPage(null === $item['representativeOfPage'] ? null : (bool) $item['representativeOfPage']);
        }

        return $media;
    }

    /**
     * @param list<mixed> $translations
     */
    private function applyMediaTranslations(MediaObject $mediaObject, array $translations): void
    {
        foreach ($translations as $translationData) {
            if (!is_array($translationData)) {
                continue;
            }

            $locale = $translationData['locale'] ?? '';
            if (!is_string($locale) || '' === $locale) {
                continue;
            }

            $translation = $mediaObject->getOrCreateTranslation($locale);
            if (!$translation instanceof MediaObjectTranslation) {
                continue;
            }

            if (array_key_exists('caption', $translationData)) {
                $caption = $translationData['caption'];
                $translation->setCaption(is_string($caption) && '' !== $caption ? $caption : null);
            }

            $translation->setTranslatable($mediaObject);
        }
    }
}
