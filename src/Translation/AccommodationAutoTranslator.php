<?php

namespace App\Translation;

use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Entity\Project;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AccommodationAutoTranslator
{
    /**
     * @var list<string>
     */
    private const LOCALIZED_FIELDS = [
        'name',
        'label',
        'highlight',
        'body',
        'review',
        'metaTitle',
        'metaDescription',
        'locationDescription',
    ];

    public function __construct(private GoogleTranslateClient $translateClient)
    {
    }

    /**
     * @param array<string, mixed> $rootPayload
     */
    public function synchronize(Accommodation $accommodation, Project $project, string $sourceLocale, array $rootPayload): void
    {
        $sourceLocale = $this->resolveSourceLocale($project, $sourceLocale, $rootPayload['translations'] ?? null);
        $this->assertEnabledLocale($project, $sourceLocale);
        $this->applyTranslationsPayload($accommodation, $project, $rootPayload['translations'] ?? null);

        $sourceTranslation = $accommodation->getTranslation($sourceLocale);
        if (!$sourceTranslation instanceof AccommodationTranslation) {
            return;
        }

        foreach ($project->getEnabledLocales() as $targetLocale) {
            if ($targetLocale === $sourceLocale) {
                continue;
            }

            $targetTranslation = $accommodation->getOrCreateTranslation($targetLocale);
            if (!$targetTranslation instanceof AccommodationTranslation) {
                continue;
            }

            $this->fillMissingFields($sourceTranslation, $targetTranslation, $sourceLocale, $targetLocale);
        }
    }

    private function resolveSourceLocale(Project $project, string $fallbackLocale, mixed $translations): string
    {
        if (!is_array($translations)) {
            return $fallbackLocale;
        }

        foreach ($translations as $translationPayload) {
            if (!is_array($translationPayload)) {
                continue;
            }

            if (($translationPayload['locale'] ?? null) === $project->getSourceLocale()) {
                return $project->getSourceLocale();
            }
        }

        return $fallbackLocale;
    }

    private function applyTranslationsPayload(Accommodation $accommodation, Project $project, mixed $translations): void
    {
        if (null === $translations) {
            return;
        }

        if (!is_array($translations)) {
            throw new BadRequestHttpException('translations must be an array.');
        }

        foreach ($translations as $translationPayload) {
            if (!is_array($translationPayload)) {
                throw new BadRequestHttpException('translations must contain only objects.');
            }

            $locale = $translationPayload['locale'] ?? null;
            if (!is_string($locale)) {
                throw new BadRequestHttpException('translations items must contain a locale.');
            }

            $this->assertEnabledLocale($project, $locale);
            $translation = $accommodation->getOrCreateTranslation($locale);
            if (!$translation instanceof AccommodationTranslation) {
                continue;
            }

            $this->applyLocalizedFields($translation, $translationPayload);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyLocalizedFields(AccommodationTranslation $translation, array $payload): void
    {
        foreach (self::LOCALIZED_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $setter = 'set'.ucfirst($field);
            $translation->{$setter}(is_string($payload[$field]) ? $payload[$field] : null);
        }
    }

    private function fillMissingFields(
        AccommodationTranslation $sourceTranslation,
        AccommodationTranslation $targetTranslation,
        string $sourceLocale,
        string $targetLocale,
    ): void {
        foreach (self::LOCALIZED_FIELDS as $field) {
            $getter = 'get'.ucfirst($field);
            $setter = 'set'.ucfirst($field);
            $sourceValue = $sourceTranslation->{$getter}();
            $targetValue = $targetTranslation->{$getter}();

            if (!is_string($sourceValue) || '' === trim($sourceValue)) {
                continue;
            }

            if (is_string($targetValue) && '' !== trim($targetValue)) {
                continue;
            }

            $targetTranslation->{$setter}(
                $this->translateClient->translate($sourceValue, $sourceLocale, $targetLocale)
            );
        }
    }

    private function assertEnabledLocale(Project $project, string $locale): void
    {
        if (!in_array($locale, $project->getEnabledLocales(), true)) {
            throw new BadRequestHttpException('Unsupported project locale.');
        }
    }
}
