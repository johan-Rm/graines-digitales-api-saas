<?php

namespace App\Tests\Functional;

use App\Entity\CategoryCode;
use App\Entity\Project;

final class DashboardAccommodationSaveTest extends FunctionalTestCase
{
    protected function shouldResetDatabaseBeforeEachTest(): bool
    {
        return true;
    }

    public function testAccommodationIdentifierIsGeneratedFromListingCategoryPair(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $category = $this->findCategoryCode($project, 'accommodation-type', 'local-commercial');
        $listing = $this->findCategoryCode($project, 'real-estate-listing', 'bien-a-vendre');
        $place = $this->findCategoryCode($project, 'accommodation-place', 'nouvelle-ville');

        $response = $client->request('POST', sprintf('/api/projects/%s/accommodations?locale=fr', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'identifier' => 'CLIENT-SHOULD-NOT-WIN',
                'name' => 'Deuxieme local commercial',
                'category' => sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $category->getId()),
                'realEstateListing' => sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $listing->getId()),
                'place' => sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $place->getId()),
                'offer' => ['price' => '720000'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertSame('BAVLC002', $payload['identifier'] ?? null);
    }

    public function testDashboardCanUpdateAccommodationByIdentifier(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $category = $this->findCategoryCode($project, 'accommodation-type', 'local-commercial');
        $listing = $this->findCategoryCode($project, 'real-estate-listing', 'bien-a-vendre');
        $place = $this->findCategoryCode($project, 'accommodation-place', 'nouvelle-ville');
        $amenityFeature = $this->findCategoryCode($project, 'amenity-feature', 'vue-degagee');
        $amenity = $this->findCategoryCode($project, 'tag', 'commerce');

        $response = $client->request('PUT', sprintf('/api/projects/%s/accommodations/BAVLC001?locale=fr', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'identifier' => 'BAVLC001',
                'isActive' => true,
                'slug' => 'superbe-local-commercial-erraounak',
                'name' => 'Superbe local commercial a Erraounak',
                'body' => '<p>Local commercial renove pour test dashboard.</p>',
                'category' => sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $category->getId()),
                'realEstateListing' => sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $listing->getId()),
                'place' => sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $place->getId()),
                'amenityFeature' => [sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $amenityFeature->getId())],
                'tags' => [sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $amenity->getId())],
                'areaSize' => '27',
                'areaTerrace' => '3',
                'floorSize' => '27',
                'landArea' => '27/30',
                'offer' => [
                    'price' => '660000',
                    'priceCurrency' => 'MAD',
                    'availability' => 'InStock',
                    'priceSpecification' => 'net vendeur',
                ],
                'qualities' => [
                    ['name' => 'Confort', 'value' => 4],
                    ['name' => 'Architecture', 'value' => 5],
                    ['name' => 'Intérieur', 'value' => 3],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('BAVLC001', $payload['identifier'] ?? null);
        self::assertSame('superbe-local-commercial-erraounak', $payload['slug'] ?? null);
        self::assertSame('3', $payload['areaTerrace'] ?? null);
        self::assertSame('27', $payload['floorSize'] ?? null);
        self::assertSame('27/30', $payload['landArea'] ?? null);
        self::assertSame('660000', $payload['offer']['price'] ?? null);

        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        self::assertSame('27', $accommodation->getAreaSize());
        self::assertSame('3', $accommodation->getAreaTerrace());
        self::assertSame('27', $accommodation->getFloorSize());
        self::assertSame('27/30', $accommodation->getLandArea());
        self::assertSame('660000', $accommodation->getOffer()['price'] ?? null);
        self::assertSame('local-commercial', $accommodation->getCategory()?->getCodeValue());
        self::assertCount(1, $accommodation->getAmenityFeature());
        self::assertSame(
            [
                ['Confort', 4],
                ['Architecture', 5],
                ['Intérieur', 3],
            ],
            array_map(
                static fn ($quality): array => [$quality->getName()->value, $quality->getValue()],
                $accommodation->getQualities()->toArray(),
            ),
        );
    }

    public function testDashboardCanUpdateAccommodationByIdentifierUsingCodeSlug(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('PUT', sprintf('/api/projects/%s/accommodations/BAVLC001?locale=fr', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'isActive' => true,
                'category' => sprintf('/api/projects/%s/category-codes/local-commercial', $project->getId()),
                'realEstateListing' => sprintf('/api/projects/%s/category-codes/bien-a-vendre', $project->getId()),
                'place' => sprintf('/api/projects/%s/category-codes/nouvelle-ville', $project->getId()),
                'amenityFeature' => [sprintf('/api/projects/%s/category-codes/vue-degagee', $project->getId())],
                'tags' => [sprintf('/api/projects/%s/category-codes/commerce', $project->getId())],
                'offer' => ['price' => '670000'],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertSame('BAVLC001', $payload['identifier'] ?? null);
        self::assertSame('670000', $payload['offer']['price'] ?? null);

        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        self::assertSame('local-commercial', $accommodation->getCategory()?->getCodeValue());
        self::assertSame('bien-a-vendre', $accommodation->getRealEstateListing()?->getCodeValue());
        self::assertSame('nouvelle-ville', $accommodation->getPlace()?->getCodeValue());
        self::assertCount(1, $accommodation->getAmenityFeature());
    }

    public function testDashboardAutoCreatesMissingAccommodationTranslationsFromSourceLocale(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('PUT', sprintf('/api/projects/%s/accommodations/BAVLC001?locale=fr', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'Local source pour traduction automatique',
                'body' => '<p>Texte source pour traduction automatique.</p>',
                'metaTitle' => 'Titre source automatique',
                'metaDescription' => 'Description source automatique',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        $englishTranslation = $accommodation->getTranslation('en');

        self::assertNotNull($englishTranslation);
        self::assertSame('Local source pour traduction automatique', $englishTranslation->getName());
        self::assertSame('<p>Texte source pour traduction automatique.</p>', $englishTranslation->getBody());
        self::assertSame('Titre source automatique', $englishTranslation->getMetaTitle());
        self::assertSame('Description source automatique', $englishTranslation->getMetaDescription());
    }

    public function testDashboardTranslationPayloadKeepsCustomValuesAndFillsOnlyMissingFields(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('blue-bay-mogador');

        $client->request('PUT', sprintf('/api/projects/%s/accommodations/FBAVVG001?locale=fr', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'translations' => [
                    [
                        'locale' => 'fr',
                        'name' => 'Local source personnalise',
                        'body' => '<p>Corps source personnalise.</p>',
                        'metaTitle' => 'Meta source',
                    ],
                    [
                        'locale' => 'en',
                        'name' => 'Custom English name',
                        'body' => '',
                    ],
                    [
                        'locale' => 'es',
                        'name' => '',
                        'metaTitle' => 'Titulo personalizado',
                    ],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $project = $this->findProjectBySlug('blue-bay-mogador');
        $accommodation = $this->findAccommodationByIdentifier('FBAVVG001', $project);
        $englishTranslation = $accommodation->getTranslation('en');
        $spanishTranslation = $accommodation->getTranslation('es');

        self::assertNotNull($englishTranslation);
        self::assertNotNull($spanishTranslation);
        self::assertSame('Custom English name', $englishTranslation->getName());
        self::assertSame('<p>Corps source personnalise.</p>', $englishTranslation->getBody());
        self::assertSame('Local source personnalise', $spanishTranslation->getName());
        self::assertSame('Titulo personalizado', $spanishTranslation->getMetaTitle());
    }

    public function testDashboardRejectsUnknownCategoryCode(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('PUT', sprintf('/api/projects/%s/accommodations/BAVLC001?locale=fr', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'category' => sprintf('/api/projects/%s/category-codes/does-not-exist', $project->getId()),
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testDashboardIdentifierRouteScopesAccommodationToProject(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $client->request('PUT', sprintf('/api/projects/%s/accommodations/BAVLC001?locale=fr', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'Should not update',
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    private function findCategoryCode(Project $project, string $inCodeSet, string $code): CategoryCode
    {
        $categoryCode = $this->entityManager()->getRepository(CategoryCode::class)->findOneBy([
            'project' => $project,
            'inCodeSet' => $inCodeSet,
            'code' => $code,
        ]);

        self::assertInstanceOf(CategoryCode::class, $categoryCode);

        return $categoryCode;
    }
}
