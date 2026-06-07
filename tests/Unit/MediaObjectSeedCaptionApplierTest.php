<?php

namespace App\Tests\Unit;

use App\Entity\MediaObject;
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

        self::assertSame('Nom de fichier', $mediaObject->getTranslation('fr')?->getCaption());
        self::assertSame('Nom de fichier', $mediaObject->getTranslation('en')?->getCaption());
        self::assertSame('Nom de fichier', $mediaObject->getTranslation('es')?->getCaption());

        $captionApplier->apply($mediaObject, 'fr', 'Terrasse de villa');

        self::assertSame('Terrasse de villa', $mediaObject->getTranslation('fr')?->getCaption());
        self::assertNull($mediaObject->getTranslation('en')?->getCaption());
        self::assertNull($mediaObject->getTranslation('es')?->getCaption());

        $autoTranslator->synchronize($mediaObject, $project);

        self::assertSame('Terrasse de villa', $mediaObject->getTranslation('fr')?->getCaption());
        self::assertSame('Terrasse de villa', $mediaObject->getTranslation('en')?->getCaption());
        self::assertSame('Terrasse de villa', $mediaObject->getTranslation('es')?->getCaption());
    }
}
