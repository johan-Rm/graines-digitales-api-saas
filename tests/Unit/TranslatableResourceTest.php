<?php

namespace App\Tests\Unit;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use PHPUnit\Framework\TestCase;

final class TranslatableResourceTest extends TestCase
{
    public function testResourceCreatesOneTranslationPerLocale(): void
    {
        $article = new Article();

        $fr = $article->getOrCreateTranslation('fr');
        $sameFr = $article->getOrCreateTranslation('fr');
        $en = $article->getOrCreateTranslation('en');

        self::assertSame($fr, $sameFr);
        self::assertNotSame($fr, $en);
        self::assertCount(2, $article->getTranslations());
        self::assertSame($article, $fr->getTranslatable());
    }

    public function testLocalizedGettersReadCurrentTranslation(): void
    {
        $article = new Article();
        $article->setSlug('riad-essaouira');
        $article->setHeadline('Riad a Essaouira');
        $article->setCurrentLocale('en');
        $article->setSlug('essaouira-riad');
        $article->setHeadline('Essaouira riad');

        self::assertSame('essaouira-riad', $article->getSlug());
        self::assertSame('Essaouira riad', $article->getHeadline());

        $article->setCurrentLocale('fr');

        self::assertSame('riad-essaouira', $article->getSlug());
        self::assertSame('Riad a Essaouira', $article->getHeadline());
    }

    public function testTranslationKnowsItsLocale(): void
    {
        $translation = new ArticleTranslation();
        $translation->setLocale('es');

        self::assertSame('es', $translation->getLocale());
    }
}
