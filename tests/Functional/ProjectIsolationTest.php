<?php

namespace App\Tests\Functional;

use App\Entity\CategoryCode;
use App\Entity\User;

final class ProjectIsolationTest extends FunctionalTestCase
{
    public function testProjectScopedResourceFromAnotherProjectIsHidden(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $riadProject = $this->findProjectBySlug('riads-medina-essaouira');
        $coastalProject = $this->findProjectBySlug('blue-bay-mogador');
        $riadPage = $this->findWebPageByProject($riadProject);

        $client->request('GET', sprintf(
            '/api/projects/%s/web-pages/%s',
            $coastalProject->getId(),
            $riadPage->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testSoftDeletedContentIsHiddenFromCollections(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $page = $this->findWebPageByProject($project);
        $pageId = (string) $page->getId();
        $admin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);

        self::assertInstanceOf(User::class, $admin);
        $page->archive($admin);
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf('/api/projects/%s/web-pages', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        $items = $payload['member'] ?? $payload;

        self::assertIsArray($items);
        self::assertNotContains($pageId, array_map(
            static fn (array $item): ?string => $item['id'] ?? null,
            $items,
        ));
    }

    public function testAccommodationFixturesUseStructuredRelations(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $accommodation = $this->findAccommodationByIdentifier('BAVR001');

        $response = $client->request('GET', sprintf('/api/projects/%s/accommodations', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        $items = $payload['member'] ?? $payload;

        self::assertIsArray($items);
        self::assertContains((string) $accommodation->getId(), array_map(
            static fn (array $item): ?string => $item['id'] ?? null,
            $items,
        ));
        self::assertInstanceOf(CategoryCode::class, $accommodation->getCategory());
        self::assertSame('accommodation-type', $accommodation->getCategory()->getInCodeSet());
        self::assertInstanceOf(CategoryCode::class, $accommodation->getRealEstateListing());
        self::assertSame('real-estate-listing', $accommodation->getRealEstateListing()->getInCodeSet());
        self::assertInstanceOf(CategoryCode::class, $accommodation->getPlace());
        self::assertSame('accommodation-place', $accommodation->getPlace()->getInCodeSet());
        self::assertCount(3, $accommodation->getAmenityFeature());
        self::assertCount(3, $accommodation->getTags());
        self::assertCount(1, $accommodation->getAssociatedMedia());
    }

    public function testAccommodationFromAnotherProjectIsHidden(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $riadProject = $this->findProjectBySlug('riads-medina-essaouira');
        $coastalAccommodation = $this->findAccommodationByIdentifier('FBAVVG001');

        $client->request('GET', sprintf(
            '/api/projects/%s/accommodations/%s',
            $riadProject->getId(),
            $coastalAccommodation->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testSoftDeletedAccommodationIsHiddenFromCollections(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $accommodation = $this->findAccommodationByIdentifier('BAVR001');
        $accommodationId = (string) $accommodation->getId();
        $admin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);

        self::assertInstanceOf(User::class, $admin);
        $accommodation->archive($admin);
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf('/api/projects/%s/accommodations', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        $items = $payload['member'] ?? $payload;

        self::assertIsArray($items);
        self::assertNotContains($accommodationId, array_map(
            static fn (array $item): ?string => $item['id'] ?? null,
            $items,
        ));
    }
}
