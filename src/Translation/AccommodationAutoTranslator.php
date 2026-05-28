<?php

namespace App\Translation;

use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Entity\Project;

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
    ];

    public function __construct(private GoogleTranslateClient $translateClient)
    {
    }

    public function synchronize(Accommodation $accommodation, Project $project): void
    {
        $sourceLocale = $project->getSourceLocale();
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

}
