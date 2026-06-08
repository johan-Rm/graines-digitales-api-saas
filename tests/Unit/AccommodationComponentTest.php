<?php

namespace App\Tests\Unit;

use App\Entity\AccommodationComponent;
use App\Entity\AccommodationComponentTranslation;
use App\Enum\AccommodationComponentType;
use PHPUnit\Framework\TestCase;

final class AccommodationComponentTest extends TestCase
{
    public function testConstructorInitializesIdentityAndAuditTrail(): void
    {
        $component = new AccommodationComponent();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $component->getId(),
        );
        self::assertSame(
            $component->getCreatedAt()->getTimestamp(),
            $component->getUpdatedAt()->getTimestamp(),
        );
        self::assertSame(AccommodationComponentType::Full, $component->getAdditionalType());
    }

    public function testLocalizedSettersDelegateToCurrentLocaleTranslation(): void
    {
        $component = new AccommodationComponent();

        $component->setCurrentLocale('fr');
        $component->setHeadline('Bienvenue');
        $component->setText('Texte FR');
        $component->setName('Intro');

        $component->setCurrentLocale('en');
        $component->setHeadline('Welcome');
        $component->setText('EN body');

        self::assertSame('Welcome', $component->getHeadline());
        self::assertSame('EN body', $component->getText());

        $component->setCurrentLocale('fr');
        self::assertSame('Bienvenue', $component->getHeadline());
        self::assertSame('Texte FR', $component->getText());
        self::assertSame('Intro', $component->getName());

        self::assertCount(2, $component->getTranslations());
        $fr = $component->getTranslation('fr');
        self::assertInstanceOf(AccommodationComponentTranslation::class, $fr);
        self::assertSame($component, $fr->getTranslatable());
    }

    public function testGetHeadlineFallsBackToNullWhenNoTranslation(): void
    {
        $component = new AccommodationComponent();

        self::assertNull($component->getHeadline());
        self::assertNull($component->getText());
        self::assertNull($component->getName());
    }

    public function testMetaIsStoredAsFreeFormObject(): void
    {
        $component = new AccommodationComponent();

        self::assertSame([], $component->getMeta());

        // The shape is intentionally open: whatever is provided is stored verbatim.
        $meta = ['reverse' => true, 'overlayMode' => 'light', 'custom' => ['nested' => 42]];
        $component->setMeta($meta);

        self::assertSame($meta, $component->getMeta());
    }

    public function testSetAssociatedMediaKeepsOnlyStrings(): void
    {
        $component = new AccommodationComponent();

        /** @phpstan-ignore-next-line intentional mixed input */
        $component->setAssociatedMedia(['uuid-a', 42, 'uuid-b', null]);

        self::assertSame(['uuid-a', 'uuid-b'], $component->getAssociatedMedia());
    }

    public function testSetAdditionalType(): void
    {
        $component = new AccommodationComponent();
        $component->setAdditionalType(AccommodationComponentType::Carousel);

        self::assertSame(AccommodationComponentType::Carousel, $component->getAdditionalType());
    }
}
