<?php

namespace App\Tests\Functional;

use App\Entity\CategoryCode;
use App\Entity\Project;

final class CategoryCodeCrudTest extends FunctionalTestCase
{
    public function testCategoryCodeCanBeCreatedForProject(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('POST', sprintf('/api/projects/%s/category-codes', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => 'atelier-artisan',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertNotEmpty($payload['@id'] ?? null);
        self::assertSame('atelier-artisan', $payload['code'] ?? null);
        self::assertSame('accommodation-type', $payload['inCodeSet'] ?? null);

        $categoryCode = $this->findCategoryCode($project, 'accommodation-type', 'atelier-artisan');
        self::assertSame((string) $project->getId(), (string) $categoryCode->getProject()?->getId());
    }

    public function testCategoryCodeDuplicateIsRejectedInsideSameProjectOnly(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $villaProject = $this->findProjectBySlug('villas-arganier-ghazoua');
        $riadProject = $this->findProjectBySlug('riads-medina-essaouira');

        $client->request('POST', sprintf('/api/projects/%s/category-codes', $villaProject->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => 'atelier-bois-test',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $client->request('POST', sprintf('/api/projects/%s/category-codes', $villaProject->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => 'atelier-bois-test',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(409);

        $response = $client->request('POST', sprintf('/api/projects/%s/category-codes', $riadProject->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => 'atelier-bois-test',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);
        self::assertSame('atelier-bois-test', $payload['code'] ?? null);
    }

    public function testCategoryCodeCreationValidatesRequiredKebabCaseCode(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('POST', sprintf('/api/projects/%s/category-codes', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => 'Local Commercial',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);

        $client->request('POST', sprintf('/api/projects/%s/category-codes', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => 'nouveau-code',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCategoryCodeCannotBeUpdated(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $categoryCode = $this->findCategoryCode($project, 'accommodation-type', 'local-commercial');

        $client->request('PUT', sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $categoryCode->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => 'local-commercial-renamed',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(405);
    }

    public function testFreeCategoryCodeCanBeDeleted(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $categoryCode = $this->createCategoryCode($client, $token, $project, 'code-a-supprimer');
        $categoryCodeId = (string) $categoryCode->getId();

        $client->request('DELETE', sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $categoryCodeId), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(204);

        $deletedCategoryCode = $this->entityManager()->getRepository(CategoryCode::class)->find($categoryCodeId);
        self::assertInstanceOf(CategoryCode::class, $deletedCategoryCode);
        self::assertNotNull($deletedCategoryCode->getDeletedAt());
    }

    public function testUsedCategoryCodeDeletionIsRejectedWithAccommodationCount(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');
        $categoryCode = $this->findCategoryCode($project, 'accommodation-type', 'local-commercial');

        $response = $client->request('DELETE', sprintf('/api/projects/%s/category-codes/%s', $project->getId(), $categoryCode->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(409);
        $payload = $response->toArray(false);

        self::assertSame('Cannot delete CategoryCode', $payload['title'] ?? null);
        self::assertSame(409, $payload['status'] ?? null);
        self::assertSame('Ce code est utilise par 1 bien(s) et ne peut pas etre supprime.', $payload['detail'] ?? null);
    }

    public function testCategoryCodeFromAnotherProjectIsHiddenOnDelete(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $riadProject = $this->findProjectBySlug('riads-medina-essaouira');
        $villaProject = $this->findProjectBySlug('villas-arganier-ghazoua');
        $categoryCode = $this->findCategoryCode($villaProject, 'accommodation-type', 'local-commercial');

        $client->request('DELETE', sprintf('/api/projects/%s/category-codes/%s', $riadProject->getId(), $categoryCode->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    private function createCategoryCode(object $client, string $token, Project $project, string $code): CategoryCode
    {
        $response = $client->request('POST', sprintf('/api/projects/%s/category-codes', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'code' => $code,
                'inCodeSet' => 'dashboard-test',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        return $this->findCategoryCode($project, 'dashboard-test', (string) $payload['code']);
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

    /**
     * @return array<string, string>
     */
    private function jsonLdHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/ld+json',
            'Content-Type' => 'application/ld+json',
        ];
    }
}
