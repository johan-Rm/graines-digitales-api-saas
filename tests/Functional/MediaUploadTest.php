<?php

namespace App\Tests\Functional;

use App\Entity\MediaObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaUploadTest extends FunctionalTestCase
{
    public function testImageUploadCreatesMediaObjectWithChecksum(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $filePath = $this->createPngFile();

        $response = $client->request('POST', sprintf('/api/projects/%s/media-objects', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'finalAlt' => 'Patio de riad',
                    'finalDescription' => 'Patio lumineux pour test upload.',
                ],
                'files' => [
                    'file' => new UploadedFile($filePath, 'riad-patio.png', 'image/png', null, true),
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);
        $id = $payload['id'] ?? null;

        self::assertIsString($id);

        $mediaObject = $this->entityManager()->getRepository(MediaObject::class)->find($id);
        self::assertInstanceOf(MediaObject::class, $mediaObject);
        self::assertSame((string) $project->getId(), (string) $mediaObject->getProject()?->getId());
        self::assertSame('image/png', $mediaObject->getMimeType());
        self::assertNotNull($mediaObject->getChecksumSha256());
    }

    public function testNonImageUploadIsRejected(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $filePath = tempnam(sys_get_temp_dir(), 'upload-test-');

        self::assertIsString($filePath);
        file_put_contents($filePath, 'not an image');

        $client->request('POST', sprintf('/api/projects/%s/media-objects', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile($filePath, 'notes.txt', 'text/plain', null, true),
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testBulkImageUploadCreatesMediaObjects(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $firstFilePath = $this->createPngFile();
        $secondFilePath = $this->createPngFile();

        $response = $client->request('POST', sprintf('/api/projects/%s/media-objects/bulk', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'finalAlt' => 'Galerie de riad',
                    'finalDescription' => 'Import bulk de galerie.',
                ],
                'files' => [
                    'files' => [
                        new UploadedFile($firstFilePath, 'riad-gallery-1.png', 'image/png', null, true),
                        new UploadedFile($secondFilePath, 'riad-gallery-2.png', 'image/png', null, true),
                    ],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertCount(2, $payload['created'] ?? []);
        self::assertSame([], $payload['errors'] ?? null);

        foreach ($payload['created'] as $createdMediaObject) {
            self::assertIsString($createdMediaObject['id'] ?? null);

            $mediaObject = $this->entityManager()->getRepository(MediaObject::class)->find($createdMediaObject['id']);
            self::assertInstanceOf(MediaObject::class, $mediaObject);
            self::assertSame((string) $project->getId(), (string) $mediaObject->getProject()?->getId());
            self::assertSame('image/png', $mediaObject->getMimeType());
            self::assertNotNull($mediaObject->getChecksumSha256());
        }
    }

    public function testBulkImageUploadReturnsPartialErrors(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $imagePath = $this->createPngFile();
        $textPath = tempnam(sys_get_temp_dir(), 'upload-test-');

        self::assertIsString($textPath);
        file_put_contents($textPath, 'not an image');

        $response = $client->request('POST', sprintf('/api/projects/%s/media-objects/bulk', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'files' => [
                        new UploadedFile($imagePath, 'riad-gallery.png', 'image/png', null, true),
                        new UploadedFile($textPath, 'notes.txt', 'text/plain', null, true),
                    ],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(207);
        $payload = $response->toArray(false);

        self::assertCount(1, $payload['created'] ?? []);
        self::assertCount(1, $payload['errors'] ?? []);
        self::assertSame(1, $payload['errors'][0]['index'] ?? null);
    }

    public function testBulkImageUploadRejectsEmptyPayload(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $client->request('POST', sprintf('/api/projects/%s/media-objects/bulk', $project->getId()), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
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
