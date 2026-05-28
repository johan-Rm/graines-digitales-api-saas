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
        $accommodation = $this->findAccommodationByIdentifier('BAVR001', $project);

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
        self::assertCount(3, $accommodation->getQualities());
        self::assertCount(1, $accommodation->getAssociatedMedia());

        $firstItem = $items[0] ?? null;
        self::assertIsArray($firstItem);
        self::assertArrayHasKey('qualities', $firstItem);
    }

    public function testAccommodationApiResponseUsesUuidRelationsAndResolvedLocaleFields(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/accommodations/%s?locale=fr',
            $project->getId(),
            $accommodation->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertArrayNotHasKey('translations', $payload);
        self::assertSame('fr', $payload['requestedLocale'] ?? null);
        self::assertSame('fr', $payload['locale'] ?? null);
        self::assertSame('Superbe local commercial a Erraounak', $payload['name'] ?? null);
        self::assertSame($accommodation->getCategory()?->getCode(), $payload['category'] ?? null);
        self::assertSame($accommodation->getRealEstateListing()?->getCode(), $payload['realEstateListing'] ?? null);
        self::assertSame($accommodation->getPlace()?->getCode(), $payload['place'] ?? null);
        self::assertSame(
            array_map(static fn ($item): string => $item->getCode(), $accommodation->getAmenityFeature()->toArray()),
            $payload['amenityFeature'] ?? null,
        );
        self::assertSame((string) $project->getId(), $payload['project'] ?? null);

        $media = $payload['associatedMedia'][0] ?? null;
        self::assertIsArray($media);
        self::assertIsString($media['mediaObject'] ?? null);
        self::assertArrayNotHasKey('project', $media);
    }

    public function testGenericApiResponsesUseUuidRelationsAndHideTranslationCollections(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $page = $this->findWebPageByProject($project);
        $categoryCode = $this->findCategoryCode($project, 'accommodation-type', 'riad');

        $pageResponse = $client->request('GET', sprintf(
            '/api/projects/%s/web-pages/%s?locale=fr',
            $project->getId(),
            $page->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $pagePayload = $pageResponse->toArray(false);
        self::assertArrayNotHasKey('translations', $pagePayload);
        self::assertSame((string) $project->getId(), $pagePayload['project'] ?? null);
        self::assertIsString($pagePayload['createdBy'] ?? null);

        $categoryResponse = $client->request('GET', sprintf(
            '/api/projects/%s/category-codes/%s?locale=fr',
            $project->getId(),
            $categoryCode->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $categoryPayload = $categoryResponse->toArray(false);
        self::assertArrayNotHasKey('translations', $categoryPayload);
        self::assertSame((string) $project->getId(), $categoryPayload['project'] ?? null);
        self::assertSame('Riad', $categoryPayload['label'] ?? null);
    }

    public function testProjectApiResponseUsesUuidRelations(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $response = $client->request('GET', sprintf('/api/projects/%s', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertIsString($payload['organization'] ?? null);
        self::assertIsString($payload['createdBy'] ?? null);
        self::assertIsString($payload['updatedBy'] ?? null);
    }

    public function testAccommodationFromAnotherProjectIsHidden(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $riadProject = $this->findProjectBySlug('riads-medina-essaouira');
        $coastalProject = $this->findProjectBySlug('blue-bay-mogador');
        $coastalAccommodation = $this->findAccommodationByIdentifier('FBAVVG001', $coastalProject);

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
        $accommodation = $this->findAccommodationByIdentifier('BAVR001', $project);
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

    public function testAccommodationTranslationsCollectionReturnsProjectTranslations(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);

        $accommodation->setCurrentLocale('es');
        $accommodation
            ->setSlug('local-comercial-erraounak')
            ->setName('Local comercial en Erraounak')
            ->setBody('Descripcion espanola del local comercial.');
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf('/api/projects/%s/accommodation-translations', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        $items = $payload['member'] ?? $payload;

        self::assertIsArray($items);

        $spanishTranslations = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['accommodation'] ?? null) === (string) $accommodation->getId()
                && ($item['locale'] ?? null) === 'es',
        ));

        self::assertCount(1, $spanishTranslations);
        self::assertSame('Local comercial en Erraounak', $spanishTranslations[0]['name'] ?? null);
    }

    public function testSoftDeletedAccommodationIsHiddenFromTranslationsCollection(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $accommodation = $this->findAccommodationByIdentifier('BAVR002', $project);
        $admin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);

        self::assertInstanceOf(User::class, $admin);
        $accommodation->archive($admin);
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf('/api/projects/%s/accommodation-translations', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        $items = $payload['member'] ?? $payload;

        self::assertIsArray($items);
        self::assertNotContains((string) $accommodation->getId(), array_map(
            static fn (array $item): ?string => $item['accommodation'] ?? null,
            $items,
        ));
    }

    public function testSuperAdminCanAccessProjectWithoutExplicitMembership(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $admin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);

        self::assertInstanceOf(User::class, $admin);

        $this->entityManager()->createQuery(
            'DELETE FROM App\Entity\ProjectMember pm WHERE pm.project = :project AND pm.user = :user',
        )
            ->setParameter('project', $project)
            ->setParameter('user', $admin)
            ->execute();

        $client->request('GET', sprintf('/api/projects/%s/web-pages', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
        ]);

        self::assertResponseIsSuccessful();
    }

    private function findCategoryCode(object $project, string $inCodeSet, string $code): CategoryCode
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
