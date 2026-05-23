<?php

namespace App\Tests\Functional;

use App\Entity\CategoryCode;
use App\Entity\Project;

final class DashboardAccommodationSaveTest extends FunctionalTestCase
{
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
                'areaSize' => 27,
                'floorSize' => '27',
                'offerPrice' => '660000',
                'offerPriceCurrency' => 'MAD',
                'offerAvailability' => 'InStock',
                'offerPriceSpecification' => 'net vendeur',
                'realEstateAgentIdentifier' => 'gd-demo-agent',
                'realEstateAgentName' => 'Johan REMY',
                'realEstateAgentPhone' => '+212600000000',
                'realEstateAgentEmail' => 'agent@example.test',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('BAVLC001', $payload['identifier'] ?? null);
        self::assertSame('superbe-local-commercial-erraounak', $payload['slug'] ?? null);
        self::assertSame('660000', $payload['offerPrice'] ?? null);

        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        self::assertSame(27, $accommodation->getAreaSize());
        self::assertSame('660000', $accommodation->getOfferPrice());
        self::assertSame('local-commercial', $accommodation->getCategory()?->getCode());
        self::assertCount(1, $accommodation->getAmenityFeature());
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
                'offerPrice' => '670000',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertSame('BAVLC001', $payload['identifier'] ?? null);
        self::assertSame('670000', $payload['offerPrice'] ?? null);

        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        self::assertSame('local-commercial', $accommodation->getCategory()?->getCode());
        self::assertSame('bien-a-vendre', $accommodation->getRealEstateListing()?->getCode());
        self::assertSame('nouvelle-ville', $accommodation->getPlace()?->getCode());
        self::assertCount(1, $accommodation->getAmenityFeature());
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
