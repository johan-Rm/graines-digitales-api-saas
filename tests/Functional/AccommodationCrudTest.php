<?php

namespace App\Tests\Functional;

use App\Entity\CategoryCode;
use App\Entity\Project;

final class AccommodationCrudTest extends FunctionalTestCase
{
    private const EDITOR = 'editor.essaouira@grainesdigitales.local';
    private const CATEGORY_CODE_IRI = '/api/projects/%s/category-codes/%s';
    private const ACCOMMODATION_IRI = '/api/projects/%s/accommodations/%s';
    private const CAPTION_SUPPLEMENTAIRE = 'Image supplémentaire';

    protected function shouldResetDatabaseBeforeEachTest(): bool
    {
        return true;
    }

    public function testGetAccommodationTranslationsShowsAllLocales(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/accommodations/%s/translations?locale=fr',
            $project->getId(),
            $accommodation->getId(),
        ), ['headers' => $this->jsonLdHeaders($token)]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        self::assertNotEmpty($payload['translations']);
        $locales = array_column($payload['translations'], 'locale');
        self::assertContains('fr', $locales);
        self::assertNotEmpty($payload['identifier'] ?? null);
    }

    public function testGetAccommodationTranslationsByIdentifierShowsAllLocales(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/accommodations/BAVLC001/translations',
            $project->getId(),
        ), ['headers' => $this->jsonLdHeaders($token)]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        self::assertNotEmpty($payload['translations']);
        $locales = array_column($payload['translations'], 'locale');
        self::assertContains('fr', $locales);
        self::assertSame('BAVLC001', $payload['identifier'] ?? null);
    }

    public function testPutAccommodationTranslationsUpdatesLocaleFields(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);

        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/accommodations/%s/translations?locale=fr',
            $project->getId(),
            $accommodation->getId(),
        ), [
            'headers' => $this->jsonLdHeaders($token, true),
            'json' => [
                'translations' => [
                    ['locale' => 'fr', 'name' => 'Local commercial mis à jour', 'body' => '<p>Corps mis à jour en français.</p>'],
                    ['locale' => 'en', 'name' => 'Updated commercial space', 'body' => '<p>Updated English body.</p>'],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Local commercial mis à jour', $byLocale['fr']['name'] ?? null);
        self::assertSame('Updated commercial space', $byLocale['en']['name'] ?? null);
    }

    public function testPostAccommodationTranslationsCreatesWithMultipleLocales(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $category = $this->findCategoryCode($project, 'accommodation-type', 'local-commercial');
        $listing = $this->findCategoryCode($project, 'real-estate-listing', 'bien-a-vendre');
        $place = $this->findCategoryCode($project, 'accommodation-place', 'nouvelle-ville');

        $response = $client->request('POST', sprintf('/api/projects/%s/accommodations/translations', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token, true),
            'json' => [
                'category' => sprintf(self::CATEGORY_CODE_IRI, $project->getId(), $category->getId()),
                'realEstateListing' => sprintf(self::CATEGORY_CODE_IRI, $project->getId(), $listing->getId()),
                'place' => sprintf(self::CATEGORY_CODE_IRI, $project->getId(), $place->getId()),
                'offer' => ['price' => '500000'],
                'translations' => [
                    ['locale' => 'fr', 'name' => 'Local commercial avec traductions', 'body' => '<p>Corps en français.</p>'],
                    ['locale' => 'en', 'name' => 'Commercial space with translations', 'body' => '<p>English body.</p>'],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertNotEmpty($payload['identifier'] ?? null);
        $this->assertNoLocalizedRootFields($payload);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Local commercial avec traductions', $byLocale['fr']['name'] ?? null);
        self::assertSame('Commercial space with translations', $byLocale['en']['name'] ?? null);
    }

    public function testPutAccommodationByIdentifierWritesToDatabase(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        $conn = $this->entityManager()->getConnection();

        $beforeSql = $conn->fetchAssociative(
            'SELECT a.floor_size, cc.code_value AS place_code
             FROM accommodation a
             JOIN category_code cc ON cc.id = a.place_id
             WHERE a.id = ?',
            [$accommodation->getId()]
        );
        self::assertNotSame('130', $beforeSql['floor_size'] ?? null, 'Precondition: floorSize must not already be 130');
        self::assertNotSame('ghazoua', $beforeSql['place_code'] ?? null, 'Precondition: place must not already be ghazoua');

        $client->request('PUT', sprintf('/api/projects/%s/accommodations/BAVLC001', $project->getId()), [
            'headers' => $this->jsonHeaders($token),
            'json' => [
                'status' => 'published',
                'isActive' => true,
                'category' => 'local-commercial',
                'realEstateListing' => 'bien-a-vendre',
                'place' => 'ghazoua',
                'floorSize' => '130',
                'amenityFeature' => ['parking', 'vitrine'],
            ],
        ]);

        self::assertResponseIsSuccessful();

        $afterSql = $conn->fetchAssociative(
            'SELECT a.floor_size, cc.code_value AS place_code
             FROM accommodation a
             JOIN category_code cc ON cc.id = a.place_id
             WHERE a.id = ?',
            [$accommodation->getId()]
        );

        self::assertSame('130', $afterSql['floor_size'] ?? null, 'floorSize must be 130 in the database');
        self::assertSame('ghazoua', $afterSql['place_code'] ?? null, 'place must be ghazoua in the database');
    }

    public function testPutAccommodationByIdentifierPersistsStructuralChanges(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/accommodations/BAVLC001',
            $project->getId(),
        ), [
            'headers' => $this->jsonHeaders($token),
            'json' => [
                'status' => 'published',
                'isActive' => true,
                'category' => 'local-commercial',
                'realEstateListing' => 'bien-a-vendre',
                'place' => 'ghazoua',
                'floorSize' => '130',
                'amenityFeature' => ['parking', 'vitrine'],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('ghazoua', $payload['place'] ?? null, 'place must be updated to ghazoua');
        self::assertSame('130', $payload['floorSize'] ?? null, 'floorSize must be updated');
        self::assertContains('parking', $payload['amenityFeature'] ?? []);
        self::assertContains('vitrine', $payload['amenityFeature'] ?? []);
        self::assertNotContains('vue-degagee', $payload['amenityFeature'] ?? []);
    }

    public function testPutTranslationsByUuidAlsoUpdatesStructuralFields(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        $conn = $this->entityManager()->getConnection();

        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/accommodations/%s/translations',
            $project->getId(),
            $accommodation->getId(),
        ), [
            'headers' => $this->jsonLdHeaders($token, true),
            'json' => [
                'status' => 'published',
                'isActive' => true,
                'category' => 'local-commercial',
                'realEstateListing' => 'bien-a-vendre',
                'place' => 'ghazoua',
                'floorSize' => '130',
                'amenityFeature' => ['parking', 'vitrine'],
                'translations' => [
                    ['locale' => 'fr', 'name' => 'Local rénové Ghazoua', 'body' => '<p>Description fr.</p>'],
                    ['locale' => 'en', 'name' => 'Renovated space Ghazoua', 'body' => '<p>English description.</p>'],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        // Champs structurels mis à jour dans la réponse
        self::assertSame('ghazoua', $payload['place'] ?? null);
        self::assertSame('130', $payload['floorSize'] ?? null);
        self::assertContains('parking', $payload['amenityFeature'] ?? []);
        self::assertNotContains('vue-degagee', $payload['amenityFeature'] ?? []);

        // Traductions présentes dans la réponse
        $byLocale = array_column($payload['translations'] ?? [], null, 'locale');
        self::assertSame('Local rénové Ghazoua', $byLocale['fr']['name'] ?? null);
        self::assertSame('Renovated space Ghazoua', $byLocale['en']['name'] ?? null);

        // Vérification directe en base
        $row = $conn->fetchAssociative(
            'SELECT a.floor_size, cc.code_value AS place_code FROM accommodation a
             JOIN category_code cc ON cc.id = a.place_id WHERE a.id = ?',
            [$accommodation->getId()]
        );
        self::assertSame('130', $row['floor_size'] ?? null, 'floorSize must be written to database');
        self::assertSame('ghazoua', $row['place_code'] ?? null, 'place must be written to database');
    }

    public function testPutAccommodationByUuidPersistsStructuralChanges(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);

        $response = $client->request('PUT', sprintf(
            self::ACCOMMODATION_IRI,
            $project->getId(),
            $accommodation->getId(),
        ), [
            'headers' => $this->jsonLdHeaders($token, true),
            'json' => [
                'status' => 'published',
                'isActive' => true,
                'category' => 'local-commercial',
                'realEstateListing' => 'bien-a-vendre',
                'place' => 'ghazoua',
                'floorSize' => '130',
                'amenityFeature' => ['parking', 'vitrine'],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('ghazoua', $payload['place'] ?? null, 'place must be updated to ghazoua via UUID route');
        self::assertSame('130', $payload['floorSize'] ?? null, 'floorSize must be updated via UUID route');
        self::assertContains('parking', $payload['amenityFeature'] ?? []);
        self::assertContains('vitrine', $payload['amenityFeature'] ?? []);
        self::assertNotContains('vue-degagee', $payload['amenityFeature'] ?? []);
    }

    /** @return array<string, string> */
    private function jsonLdHeaders(string $token, bool $withContentType = false): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/ld+json',
        ];

        if ($withContentType) {
            $headers['Content-Type'] = 'application/ld+json';
        }

        return $headers;
    }

    /** @return array<string, string> */
    private function jsonHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ];
    }

    public function testPutAddsOneMoreMediaViaIdentifierRoute(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        // BAVLC001 starts with 1 media from fixtures — load directly from DB.
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        $initialMedia = $accommodation->getAssociatedMedia()->toArray();
        self::assertNotEmpty($initialMedia, 'Fixture must have at least 1 media on BAVLC001');

        // Build the existing media payload items from current DB state.
        $existingMediaPayload = array_values(array_map(
            static fn (\App\Entity\AccommodationMedia $m): array => [
                'mediaObject' => (string) $m->getMediaObject()->getId(),
                'position' => $m->getPosition(),
                'keywords' => $m->getKeywords(),
                'representativeOfPage' => $m->isRepresentativeOfPage(),
            ],
            $initialMedia,
        ));

        // Create a second media object in the project (fixtures only provide one).
        $user = $this->entityManager()->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => self::EDITOR]);
        self::assertNotNull($user);

        $extraMedia = new \App\Entity\MediaObject();
        $extraMedia->setProject($project);
        $extraMedia->setOriginalFilename('extra-test-image.jpg');
        $extraMedia->setStatus(\App\Enum\MediaStatus::Ready);
        $extraMedia->markCreatedBy($user);
        $extraMedia->touch($user);
        $this->entityManager()->persist($extraMedia);
        $this->entityManager()->flush();

        $newMediaItem = [
            'mediaObject' => (string) $extraMedia->getId(),
            'position' => count($existingMediaPayload) + 1,
            'caption' => self::CAPTION_SUPPLEMENTAIRE,
            'keywords' => ['gallery'],
            'representativeOfPage' => false,
        ];

        // PUT with all existing items + 1 new one — the real dashboard scenario.
        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/accommodations/BAVLC001?locale=fr',
            $project->getId(),
        ), [
            'headers' => $this->jsonHeaders($token),
            'json' => [
                'category' => 'local-commercial',
                'realEstateListing' => 'bien-a-vendre',
                'place' => 'ghazoua',
                'associatedMedia' => array_merge($existingMediaPayload, [$newMediaItem]),
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        $expectedCount = count($initialMedia) + 1;
        self::assertCount($expectedCount, $payload['associatedMedia'] ?? [], 'Response must include all media');

        $this->entityManager()->clear();
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        self::assertCount($expectedCount, $accommodation->getAssociatedMedia(), 'DB must persist the new total');

        // Caption is now stored in MediaObjectTranslation, not AccommodationMedia.
        $rows = $this->entityManager()->getConnection()->fetchAllAssociative(
            'SELECT caption FROM media_object_translation WHERE media_object_id = ? AND locale = ?',
            [(string) $extraMedia->getId(), 'fr'],
        );
        self::assertNotEmpty($rows, 'MediaObjectTranslation must exist for the new media');
        self::assertSame(self::CAPTION_SUPPLEMENTAIRE, $rows[0]['caption'], 'Caption must be written to MediaObjectTranslation');
    }

    public function testPutAssociatedMediaPersistsToDatabase(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        $mediaObject = $this->findMediaObjectByProject($project);

        $response = $client->request('PUT', sprintf(
            self::ACCOMMODATION_IRI,
            $project->getId(),
            $accommodation->getId(),
        ), [
            'headers' => $this->jsonHeaders($token),
            'json' => [
                'category' => 'local-commercial',
                'realEstateListing' => 'bien-a-vendre',
                'place' => 'ghazoua',
                'associatedMedia' => [
                    [
                        'mediaObject' => (string) $mediaObject->getId(),
                        'position' => 1,
                        'caption' => 'Test caption',
                        'keywords' => ['gallery'],
                        'representativeOfPage' => true,
                    ],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertCount(1, $payload['associatedMedia'] ?? [], 'PUT response must include associatedMedia');

        $this->entityManager()->clear();
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        self::assertCount(1, $accommodation->getAssociatedMedia(), 'PUT must persist associatedMedia to DB');

        /** @var \App\Entity\AccommodationMedia $first */
        $first = $accommodation->getAssociatedMedia()->first();
        self::assertSame(1, $first->getPosition());
        // Caption is now stored in MediaObjectTranslation, not AccommodationMedia.
        $captionRows = $this->entityManager()->getConnection()->fetchAllAssociative(
            'SELECT caption FROM media_object_translation WHERE media_object_id = ?',
            [(string) $first->getMediaObject()->getId()],
        );
        self::assertContains('Test caption', array_column($captionRows, 'caption'), 'Caption must be written to MediaObjectTranslation');
        self::assertTrue($first->isRepresentativeOfPage());
        self::assertSame((string) $mediaObject->getId(), (string) $first->getMediaObject()->getId());
    }

    public function testPutAssociatedMediaPersistsToDatabaseJsonLd(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        $mediaObject = $this->findMediaObjectByProject($project);

        $response = $client->request('PUT', sprintf(
            self::ACCOMMODATION_IRI,
            $project->getId(),
            $accommodation->getId(),
        ), [
            'headers' => $this->jsonLdHeaders($token, true),
            'json' => [
                'category' => 'local-commercial',
                'realEstateListing' => 'bien-a-vendre',
                'place' => 'ghazoua',
                'associatedMedia' => [
                    [
                        'mediaObject' => (string) $mediaObject->getId(),
                        'position' => 1,
                        'caption' => 'Test JSON-LD caption',
                        'keywords' => ['gallery'],
                        'representativeOfPage' => true,
                    ],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertCount(1, $payload['associatedMedia'] ?? [], 'PUT JSON-LD response must include associatedMedia');

        $this->entityManager()->clear();
        $accommodation = $this->findAccommodationByIdentifier('BAVLC001', $project);
        self::assertCount(1, $accommodation->getAssociatedMedia(), 'PUT JSON-LD must persist associatedMedia to DB');
    }

    private function findCategoryCode(Project $project, string $inCodeSet, string $code): CategoryCode
    {
        $categoryCode = $this->entityManager()->getRepository(CategoryCode::class)->findOneBy([
            'project' => $project,
            'inCodeSet' => $inCodeSet,
            'codeValue' => $code,
        ]);

        self::assertInstanceOf(CategoryCode::class, $categoryCode);

        return $categoryCode;
    }
}
