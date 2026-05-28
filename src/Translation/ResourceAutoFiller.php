<?php

namespace App\Translation;

use App\Entity\ResourceTranslation;

final class ResourceAutoFiller
{
    public function fill(ResourceTranslation $translation): void
    {
        $reflection = new \ReflectionClass($translation);

        foreach ($reflection->getProperties() as $property) {
            $attrs = $property->getAttributes(AutoFill::class);
            if ([] === $attrs) {
                continue;
            }

            /** @var AutoFill $autoFill */
            $autoFill = $attrs[0]->newInstance();
            $field = $property->getName();
            $getter = 'get'.ucfirst($field);
            $setter = 'set'.ucfirst($field);
            $sourceGetter = 'get'.ucfirst($autoFill->from);

            $currentValue = $translation->{$getter}();
            if (is_string($currentValue) && '' !== trim($currentValue)) {
                continue;
            }

            $sourceValue = $translation->{$sourceGetter}();
            if (!is_string($sourceValue) || '' === trim($sourceValue)) {
                continue;
            }

            $value = null !== $autoFill->maxLength
                ? rtrim(mb_substr(strip_tags($sourceValue), 0, $autoFill->maxLength))
                : $sourceValue;

            $translation->{$setter}($value);
        }
    }
}
