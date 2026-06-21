<?php

namespace App\Tests\Functional;

use App\Entity\MediaObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaObjectCrudTest extends FunctionalTestCase
{
    protected function shouldResetDatabaseBeforeEachTest(): bool
    {
        return true;
    }

    public function testGlobalMediaObjectCollectionIsAccessible(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $mediaObject = $this->findMediaObjectByProject($project)->setFilePath('riad-patio.jpg');
        $this->entityManager()->flush();

        $response = $client->request('GET', '/api/media-objects', [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $items = $payload['hydra:member'] ?? $payload['member'] ?? $payload;
        self::assertIsArray($items);
        self::assertNotEmpty($items);
        self::assertContains('/images/'.$mediaObject->getFilePath(), array_column($items, 'contentUrl'));
    }

    public function testGlobalMediaObjectCollectionRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/media-objects', [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testProjectMediaObjectCollectionReturnsItems(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $mediaObject = $this->findMediaObjectByProject($project)->setFilePath('riad-patio.jpg');
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf('/api/projects/%s/media-objects', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $items = $payload['hydra:member'] ?? $payload['member'] ?? $payload;
        self::assertIsArray($items);
        self::assertNotEmpty($items);

        $first = is_array($items[0] ?? null) ? $items[0] : reset($items);
        self::assertIsArray($first, 'First collection item should be an array');
        self::assertContains('/images/'.$mediaObject->getFilePath(), array_column($items, 'contentUrl'));
        self::assertArrayNotHasKey('translations', $first, 'translations must be hidden on collection GET');
    }

    public function testGetMediaObjectItemReturnsDetails(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $mediaObject = $this->findMediaObjectByProject($project);
        $mediaObject->setFilePath('riad-patio.jpg');
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/media-objects/%s',
            $project->getId(),
            $mediaObject->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertNotEmpty($payload['id'] ?? null);
        self::assertSame('/images/'.$mediaObject->getFilePath(), $payload['contentUrl'] ?? null);
        self::assertArrayNotHasKey('translations', $payload, 'translations must be hidden on standard GET');
    }

    public function testGetMediaObjectTranslationsShowsLocales(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $mediaObject = $this->findMediaObjectByProject($project);

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/media-objects/%s/translations',
            $project->getId(),
            $mediaObject->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        self::assertIsArray($payload['translations']);
    }

    public function testPutMediaObjectUpdatesMetadata(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $mediaObject = $this->findMediaObjectByProject($project);

        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/media-objects/%s?locale=fr',
            $project->getId(),
            $mediaObject->getId(),
        ), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'aiSuggestedAlt' => 'Patio lumineux du riad',
                'finalFilename' => 'patio-riad-updated.jpg',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('Patio lumineux du riad', $payload['aiSuggestedAlt'] ?? null);
        self::assertSame('patio-riad-updated.jpg', $payload['finalFilename'] ?? null);
    }

    public function testPutMediaObjectTranslationsUpdatesCaption(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $mediaObject = $this->findMediaObjectByProject($project);

        $response = $client->request('PUT', sprintf(
            '/api/projects/%s/media-objects/%s/translations',
            $project->getId(),
            $mediaObject->getId(),
        ), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'translations' => [
                    ['locale' => 'fr', 'caption' => 'Patio lumineux mis à jour'],
                    ['locale' => 'en', 'caption' => 'Updated bright patio'],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Patio lumineux mis à jour', $byLocale['fr']['caption'] ?? null);
        self::assertSame('Updated bright patio', $byLocale['en']['caption'] ?? null);
    }

    public function testPostMediaObjectTranslationsAppliesCaption(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $mediaObject = $this->findMediaObjectByProject($project);

        $response = $client->request('POST', sprintf('/api/projects/%s/media-objects/translations', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'resourceId' => (string) $mediaObject->getId(),
                'translations' => [
                    ['locale' => 'fr', 'caption' => 'Légende appliquée en français'],
                    ['locale' => 'en', 'caption' => 'Applied English caption'],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame((string) $mediaObject->getId(), $payload['id'] ?? null);
        $this->assertNoLocalizedRootFields($payload);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Légende appliquée en français', $byLocale['fr']['caption'] ?? null);
        self::assertSame('Applied English caption', $byLocale['en']['caption'] ?? null);
    }

    public function testDeleteMediaObjectRemovesIt(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $filePath = $this->createPngFile();
        $uploadResponse = $client->request('POST', sprintf('/api/projects/%s/media-objects', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile($filePath, 'to-delete.png', 'image/png', null, true),
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $mediaObjectId = $uploadResponse->toArray(false)['id'];

        $client->request('DELETE', sprintf(
            '/api/projects/%s/media-objects/%s',
            $project->getId(),
            $mediaObjectId,
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(204);

        $this->entityManager()->clear();
        $deleted = $this->entityManager()->getRepository(MediaObject::class)->find($mediaObjectId);
        self::assertNull($deleted);
    }

    private function createPngFile(): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'upload-test-');
        self::assertIsString($filePath);
        file_put_contents($filePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true,
        ));

        return $filePath;
    }
}
