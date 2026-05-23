<?php

namespace App\Tests\Functional;

use App\Entity\AccommodationTranslation;
use App\Entity\ArticleTranslation;
use App\Entity\WebPageTranslation;

final class SlugGenerationTest extends FunctionalTestCase
{
    public function testOrganizationAndProjectSlugsAreGeneratedFromName(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');

        $organizationResponse = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'name' => 'Slug Test Organization',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $organizationPayload = $organizationResponse->toArray(false);

        self::assertSame('slug-test-organization', $organizationPayload['slug'] ?? null);
        self::assertIsString($organizationPayload['id'] ?? null);

        $projectResponse = $client->request('POST', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'organization' => '/api/organizations/'.$organizationPayload['id'],
                'name' => 'Slug Test Project',
                'sourceLocale' => 'fr',
                'enabledLocales' => ['fr', 'en'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $projectPayload = $projectResponse->toArray(false);

        self::assertSame('slug-test-project', $projectPayload['slug'] ?? null);
    }

    public function testExplicitSlugsAreNormalized(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');

        $response = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'name' => 'Explicit Organization',
                'slug' => 'Déjà SLUG Custom',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertSame('deja-slug-custom', $payload['slug'] ?? null);
    }

    public function testTranslatedContentSlugsAreGeneratedFromMainTitleField(): void
    {
        $articleTranslation = new ArticleTranslation();
        $articleTranslation->setHeadline('Actualités Essaouira & Médina');

        $webPageTranslation = new WebPageTranslation();
        $webPageTranslation->setHeadline('Accueil My Little Kasbah');

        $accommodationTranslation = new AccommodationTranslation();
        $accommodationTranslation->setName('Riad lumineux dans la Médina');

        self::assertSame('actualites-essaouira-medina', $articleTranslation->getSlug());
        self::assertSame('accueil-my-little-kasbah', $webPageTranslation->getSlug());
        self::assertSame('riad-lumineux-dans-la-medina', $accommodationTranslation->getSlug());
    }
}
