<?php

namespace App\Tests\Functional;

final class WebPageCrudTest extends FunctionalTestCase
{
    protected function shouldResetDatabaseBeforeEachTest(): bool
    {
        return true;
    }

    public function testWebPageCanBeCreatedViaPost(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('POST', sprintf('/api/projects/%s/web-pages', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'status' => 'draft',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertNotEmpty($payload['id'] ?? null);
        self::assertSame('draft', $payload['status'] ?? null);
    }

    public function testPostWebPageTranslationsCreatesWithMultipleLocales(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('POST', sprintf('/api/projects/%s/web-pages/translations', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'status' => 'draft',
                'translations' => [
                    ['locale' => 'fr', 'headline' => 'Page bilingue en français', 'metaTitle' => 'Titre SEO français'],
                    ['locale' => 'en', 'headline' => 'Bilingual page in English', 'metaTitle' => 'English SEO title'],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertNotEmpty($payload['id'] ?? null);
        $this->assertNoLocalizedRootFields($payload);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Page bilingue en français', $byLocale['fr']['headline'] ?? null);
        self::assertSame('Bilingual page in English', $byLocale['en']['headline'] ?? null);
    }

    public function testGetWebPageTranslationsShowsAllLocales(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $page = $this->findWebPageByProject($project);

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/web-pages/%s/translations?locale=fr',
            $project->getId(),
            $page->getId(),
        ), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        $locales = array_column($payload['translations'], 'locale');
        self::assertContains('fr', $locales);
        self::assertContains('en', $locales);
    }

    public function testPutWebPageUpdatesContent(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $page = $this->findWebPageByProject($project);

        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/web-pages/%s?locale=fr',
            $project->getId(),
            $page->getId(),
        ), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'inLanguage' => 'fr',
                'headline' => 'Page mise à jour via PUT',
                'metaTitle' => 'Titre SEO mis à jour',
                'body' => '<p>Corps de page mis à jour.</p>',
                'status' => 'published',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('Page mise à jour via PUT', $payload['headline'] ?? null);
        self::assertSame('Titre SEO mis à jour', $payload['metaTitle'] ?? null);
        self::assertSame('published', $payload['status'] ?? null);
    }

    public function testPutWebPageTranslationsUpdatesAllLocales(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $page = $this->findWebPageByProject($project);

        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/web-pages/%s/translations?locale=fr',
            $project->getId(),
            $page->getId(),
        ), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'translations' => [
                    ['locale' => 'fr', 'headline' => 'Titre mis à jour en français', 'metaTitle' => 'SEO français mis à jour'],
                    ['locale' => 'en', 'headline' => 'Updated English headline', 'metaTitle' => 'Updated English SEO'],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Titre mis à jour en français', $byLocale['fr']['headline'] ?? null);
        self::assertSame('Updated English headline', $byLocale['en']['headline'] ?? null);
    }
}
