<?php

namespace App\Seed;

use App\Entity\MediaObject;
use App\Entity\MediaObjectTranslation;

final readonly class MediaObjectSeedCaptionApplier
{
    public function apply(MediaObject $mediaObject, string $sourceLocale, string $caption): void
    {
        $mediaObject->setCurrentLocale($sourceLocale);
        $translation = $mediaObject->getOrCreateTranslation($sourceLocale);
        if (!$translation instanceof MediaObjectTranslation) {
            return;
        }

        $translation->setCaption($caption);
        $translation->setTranslatable($mediaObject);
        $this->clearTranslatedCaptions($mediaObject, $sourceLocale);
    }

    public function clearTranslatedCaptions(MediaObject $mediaObject, string $sourceLocale): void
    {
        foreach ($mediaObject->getTranslations() as $translation) {
            if ($translation instanceof MediaObjectTranslation && $translation->getLocale() !== $sourceLocale) {
                $translation->setCaption(null);
            }
        }
    }
}
