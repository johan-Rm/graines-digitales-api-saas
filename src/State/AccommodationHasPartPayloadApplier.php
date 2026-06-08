<?php

declare(strict_types=1);

namespace App\State;

use App\Entity\Accommodation;
use App\Entity\AccommodationComponent;
use App\Entity\AccommodationComponentTranslation;
use App\Entity\Project;
use App\Enum\AccommodationComponentType;
use App\Security\CurrentUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;
use ValueError;

/**
 * Reconciles the `hasPart` content blocks of an Accommodation from the raw request body.
 *
 * The collection is fully rebuilt on each write (clear + recreate), mirroring the
 * associatedMedia applier. Structural fields (additionalType, position, associatedMedia,
 * meta) are shared across locales; headline/text/name are written on the current-locale
 * translation, with an optional per-item `translations` array for multi-locale writes.
 */
final readonly class AccommodationHasPartPayloadApplier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function apply(Accommodation $accommodation, Project $project, array $payload, ?string $currentLocale = null): void
    {
        if (!array_key_exists('hasPart', $payload)) {
            return;
        }

        if (!is_array($payload['hasPart'])) {
            throw new BadRequestHttpException('hasPart must be an array.');
        }

        foreach ($accommodation->getHasPart() as $component) {
            if ($this->entityManager->contains($component)) {
                $this->entityManager->remove($component);
            }
        }
        $accommodation->clearHasPart();

        $actor = $this->currentUserProvider->getUser();
        $locale = $currentLocale ?? $accommodation->getCurrentLocale();

        $position = 0;
        foreach ($payload['hasPart'] as $item) {
            if (!is_array($item)) {
                throw new BadRequestHttpException('Each hasPart item must be an object.');
            }

            $component = $this->buildComponent($item, $position);
            $accommodation->addComponent($component);

            $this->applyTranslations($component, $item, $locale);

            $component->markCreatedBy($actor);
            $component->touch($actor);
            foreach ($component->getTranslations() as $translation) {
                if ($translation instanceof AccommodationComponentTranslation) {
                    $translation->setTranslatable($component);
                    $translation->touch($actor);
                }
            }

            ++$position;
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildComponent(array $item, int $defaultPosition): AccommodationComponent
    {
        $component = new AccommodationComponent();
        $component->setAdditionalType($this->resolveType($item));

        $position = $item['position'] ?? null;
        $component->setPosition(is_numeric($position) ? (int) $position : $defaultPosition);

        if (array_key_exists('associatedMedia', $item)) {
            $component->setAssociatedMedia($this->resolveAssociatedMedia($item['associatedMedia']));
        }

        if (array_key_exists('meta', $item)) {
            if (!is_array($item['meta'])) {
                throw new BadRequestHttpException('hasPart[].meta must be an object.');
            }
            $component->setMeta($item['meta']);
        }

        return $component;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveType(array $item): AccommodationComponentType
    {
        $raw = $item['additionalType'] ?? null;
        if (!is_string($raw) || '' === $raw) {
            throw new BadRequestHttpException('hasPart[].additionalType is required.');
        }

        try {
            return AccommodationComponentType::from($raw);
        } catch (ValueError) {
            throw new BadRequestHttpException('Unknown hasPart[].additionalType.');
        }
    }

    /**
     * @return list<string>
     */
    private function resolveAssociatedMedia(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new BadRequestHttpException('hasPart[].associatedMedia must be an array.');
        }

        $uuids = [];
        foreach ($raw as $reference) {
            if (!is_string($reference)) {
                throw new BadRequestHttpException('hasPart[].associatedMedia must contain only UUIDs or IRIs.');
            }

            $id = basename($reference);
            if (!Uuid::isValid($id)) {
                throw new BadRequestHttpException('hasPart[].associatedMedia must contain valid UUIDs or IRIs.');
            }

            $uuids[] = $id;
        }

        return $uuids;
    }

    /**
     * Writes headline/text/name on the current-locale translation, then applies any
     * explicit per-item `translations` array (locale-keyed) for multi-locale writes.
     *
     * @param array<string, mixed> $item
     */
    private function applyTranslations(AccommodationComponent $component, array $item, string $currentLocale): void
    {
        if ($this->hasLocalizedField($item)) {
            $component->setCurrentLocale($currentLocale);
            $this->writeTranslationFields($component, $item, $currentLocale);
        }

        $translations = $item['translations'] ?? null;
        if (!is_array($translations)) {
            return;
        }

        foreach ($translations as $translationData) {
            if (!is_array($translationData)) {
                continue;
            }

            $locale = $translationData['locale'] ?? '';
            if (!is_string($locale) || '' === $locale) {
                continue;
            }

            $this->writeTranslationFields($component, $translationData, $locale);
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function writeTranslationFields(AccommodationComponent $component, array $fields, string $locale): void
    {
        $translation = $component->getOrCreateTranslation($locale);
        if (!$translation instanceof AccommodationComponentTranslation) {
            return;
        }

        foreach (['name', 'headline', 'text'] as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }
            $value = $fields[$field];
            $setter = 'set'.ucfirst($field);
            $translation->{$setter}(is_string($value) && '' !== $value ? $value : null);
        }

        $translation->setTranslatable($component);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function hasLocalizedField(array $item): bool
    {
        return array_key_exists('name', $item)
            || array_key_exists('headline', $item)
            || array_key_exists('text', $item);
    }
}
