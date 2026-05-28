<?php

namespace App\Translation;

use App\Entity\Project;
use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;

final readonly class ResourceAutoTranslator
{
    public function __construct(private GoogleTranslateClient $translateClient)
    {
    }

    public function synchronize(TranslatableResource $resource, Project $project): void
    {
        $sourceLocale = $project->getSourceLocale();
        $sourceTranslation = $resource->getTranslation($sourceLocale);

        if (!$sourceTranslation instanceof ResourceTranslation) {
            return;
        }

        $fields = $this->getAutoTranslateFields($sourceTranslation);

        if ([] === $fields) {
            return;
        }

        foreach ($project->getEnabledLocales() as $targetLocale) {
            if ($targetLocale === $sourceLocale) {
                continue;
            }

            $targetTranslation = $resource->getOrCreateTranslation($targetLocale);
            $this->fillMissingFields($sourceTranslation, $targetTranslation, $fields, $sourceLocale, $targetLocale);
        }
    }

    /**
     * @return list<string>
     */
    private function getAutoTranslateFields(ResourceTranslation $translation): array
    {
        $fields = [];
        $reflection = new \ReflectionClass($translation);

        foreach ($reflection->getProperties() as $property) {
            if ([] !== $property->getAttributes(AutoTranslate::class)) {
                $fields[] = $property->getName();
            }
        }

        return $fields;
    }

    /**
     * @param list<string> $fields
     */
    private function fillMissingFields(
        ResourceTranslation $source,
        ResourceTranslation $target,
        array $fields,
        string $sourceLocale,
        string $targetLocale,
    ): void {
        foreach ($fields as $field) {
            $getter = 'get'.ucfirst($field);
            $setter = 'set'.ucfirst($field);
            $sourceValue = $source->{$getter}();
            $targetValue = $target->{$getter}();

            if (!is_string($sourceValue) || '' === trim($sourceValue)) {
                continue;
            }

            if (is_string($targetValue) && '' !== trim($targetValue)) {
                continue;
            }

            $target->{$setter}(
                $this->translateClient->translate($sourceValue, $sourceLocale, $targetLocale)
            );
        }
    }
}
