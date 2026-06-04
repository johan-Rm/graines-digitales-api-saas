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
                'codeValue' => 'atelier-artisan',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertNotEmpty($payload['@id'] ?? null);
        self::assertSame('atelier-artisan', $payload['codeValue'] ?? null);
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
                'codeValue' => 'atelier-bois-test',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $client->request('POST', sprintf('/api/projects/%s/category-codes', $villaProject->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'codeValue' => 'atelier-bois-test',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(409);

        $response = $client->request('POST', sprintf('/api/projects/%s/category-codes', $riadProject->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'codeValue' => 'atelier-bois-test',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);
        self::assertSame('atelier-bois-test', $payload['codeValue'] ?? null);
    }

    public function testCategoryCodeCreationValidatesRequiredKebabCaseCode(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('POST', sprintf('/api/projects/%s/category-codes', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'codeValue' => 'Local Commercial',
                'inCodeSet' => 'accommodation-type',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);

        $client->request('POST', sprintf('/api/projects/%s/category-codes', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'codeValue' => 'nouveau-code',
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
        self::assertNull($deletedCategoryCode);
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
                'codeValue' => $code,
                'inCodeSet' => 'dashboard-test',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        return $this->findCategoryCode($project, 'dashboard-test', (string) $payload['codeValue']);
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

    public function testCategoryCodeGetResponsesAreCorrect(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $post = $client->request('POST', sprintf('/api/projects/%s/category-codes/translations', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'inCodeSet' => 'accommodation-type',
                'translations' => [
                    ['locale' => 'fr', 'label' => 'Atelier artisanal', 'text' => 'Texte long atelier'],
                    ['locale' => 'en', 'label' => 'Artisan workshop', 'text' => 'Workshop long text'],
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $id = $post->toArray(false)['id'];

        $std = $client->request('GET', sprintf('/api/projects/%s/category-codes/%s?locale=fr', $project->getId(), $id), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ])->toArray(false);

        self::assertResponseIsSuccessful();
        self::assertSame('atelier-artisanal', $std['codeValue'] ?? null);
        self::assertSame('Atelier artisanal', $std['label'] ?? null);
        self::assertSame('Texte long atelier', $std['text'] ?? null);
        self::assertSame('fr', $std['locale'] ?? null);
        self::assertArrayNotHasKey('translations', $std, 'translations must be hidden on standard GET');

        $trans = $client->request('GET', sprintf('/api/projects/%s/category-codes/%s/translations?locale=fr', $project->getId(), $id), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ])->toArray(false);

        self::assertResponseIsSuccessful();
        $this->assertNoLocalizedRootFields($trans);
        $locales = array_column($trans['translations'], 'locale');
        self::assertContains('fr', $locales);
        self::assertContains('en', $locales);
        self::assertContains('es', $locales);
        $byLocale = array_column($trans['translations'], null, 'locale');
        self::assertSame('Texte long atelier', $byLocale['fr']['text'] ?? null);
    }

    public function testGetNonExistentIdReturns404(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('GET', sprintf(
            '/api/projects/%s/category-codes/019e78f4-b66e-7649-8628-3f2f1338da3e',
            $project->getId()
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCategoryCodeCollectionReturnsProjectItems(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('GET', sprintf('/api/projects/%s/category-codes', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $items = $payload['hydra:member'] ?? $payload['member'] ?? $payload;
        self::assertIsArray($items);
        self::assertNotEmpty($items);

        $first = is_array($items[0] ?? null) ? $items[0] : reset($items);
        self::assertNotEmpty($first['codeValue'] ?? null);
        self::assertNotEmpty($first['inCodeSet'] ?? null);
        self::assertArrayHasKey('text', $first);
        self::assertArrayNotHasKey('translations', $first, 'translations must be hidden on collection GET');
    }

    public function testPutCategoryCodeTranslationsUpdatesTextOnly(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $created = $client->request('POST', sprintf('/api/projects/%s/category-codes/translations', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'inCodeSet' => 'tag',
                'translations' => [
                    ['locale' => 'fr', 'label' => 'Étiquette initiale', 'text' => 'Texte initial'],
                    ['locale' => 'en', 'label' => 'Initial tag', 'text' => 'Initial text'],
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $id = $created->toArray(false)['id'];

        $response = $client->request('PUT', sprintf('/api/projects/%s/category-codes/%s/translations?locale=fr', $project->getId(), $id), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'translations' => [
                    ['locale' => 'fr', 'text' => 'Texte public mis à jour'],
                    ['locale' => 'en', 'text' => 'Updated public text'],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        $this->assertNoLocalizedRootFields($payload);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Étiquette initiale', $byLocale['fr']['label'] ?? null);
        self::assertSame('Initial tag', $byLocale['en']['label'] ?? null);
        self::assertSame('Texte public mis à jour', $byLocale['fr']['text'] ?? null);
        self::assertSame('Updated public text', $byLocale['en']['text'] ?? null);
        self::assertArrayNotHasKey('text', $payload, '/translations must not flatten a single locale at the root');
    }

    public function testCategoryCodeLabelCannotBeUpdatedThroughTranslations(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('POST', sprintf('/api/projects/%s/category-codes/translations', $project->getId()), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'inCodeSet' => 'tag',
                'translations' => [
                    ['locale' => 'fr', 'label' => 'Libellé stable', 'text' => 'Texte public'],
                    ['locale' => 'en', 'label' => 'Stable label', 'text' => 'Public text'],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);
        $id = $payload['id'];

        self::assertSame('libelle-stable', $payload['codeValue'] ?? null);
        $byLocale = array_column($payload['translations'], null, 'locale');
        self::assertSame('Libellé stable', $byLocale['fr']['label'] ?? null);
        self::assertSame('Texte public', $byLocale['fr']['text'] ?? null);

        $client->request('PUT', sprintf('/api/projects/%s/category-codes/%s/translations?locale=fr', $project->getId(), $id), [
            'headers' => $this->jsonLdHeaders($token),
            'json' => [
                'translations' => [
                    ['locale' => 'fr', 'label' => 'Libellé interdit', 'text' => 'Texte public modifié'],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}
