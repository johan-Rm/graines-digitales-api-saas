<?php

namespace App\Tests\Unit;

use App\Entity\MediaObject;
use App\Entity\MediaObjectTranslation;
use App\Entity\Project;
use App\Seed\MediaObjectSeedCaptionApplier;
use App\Translation\GoogleTranslateClient;
use App\Translation\ResourceAutoTranslator;
use PHPUnit\Framework\TestCase;

final class MediaObjectSeedCaptionApplierTest extends TestCase
{
    public function testApplyClearsTranslatedCaptionsSoTheyCanBeRegenerated(): void
    {
        $project = (new Project())
            ->setSourceLocale('fr')
            ->setEnabledLocales(['fr', 'en', 'es']);
        $mediaObject = (new MediaObject())->setProject($project);
        $captionApplier = new MediaObjectSeedCaptionApplier();
        $autoTranslator = new ResourceAutoTranslator(new GoogleTranslateClient());

        $captionApplier->apply($mediaObject, 'fr', 'Nom de fichier');
        $autoTranslator->synchronize($mediaObject, $project);

        self::assertSame('Nom de fichier', $this->translation($mediaObject, 'fr')->getCaption());
        self::assertSame('Nom de fichier', $this->translation($mediaObject, 'en')->getCaption());
        self::assertSame('Nom de fichier', $this->translation($mediaObject, 'es')->getCaption());

        $captionApplier->apply($mediaObject, 'fr', 'Terrasse de villa');

        self::assertSame('Terrasse de villa', $this->translation($mediaObject, 'fr')->getCaption());
        self::assertNull($this->translation($mediaObject, 'en')->getCaption());
        self::assertNull($this->translation($mediaObject, 'es')->getCaption());

        $autoTranslator->synchronize($mediaObject, $project);

        self::assertSame('Terrasse de villa', $this->translation($mediaObject, 'fr')->getCaption());
        self::assertSame('Terrasse de villa', $this->translation($mediaObject, 'en')->getCaption());
        self::assertSame('Terrasse de villa', $this->translation($mediaObject, 'es')->getCaption());
    }

    private function translation(MediaObject $mediaObject, string $locale): MediaObjectTranslation
    {
        $translation = $mediaObject->getTranslation($locale);

        self::assertInstanceOf(MediaObjectTranslation::class, $translation);

        return $translation;
    }
}
