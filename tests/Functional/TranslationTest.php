<?php

namespace App\Tests\Functional;

final class TranslationTest extends FunctionalTestCase
{
    public function testTranslationCanBeCreatedAndReadThroughProjectRoute(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $page = $this->findWebPageByProject($project);

        $page->setCurrentLocale('en');
        $page
            ->setSlug('home')
            ->setHeadline('Medina riads in Essaouira')
            ->setBody('A curated selection of quiet riads in Essaouira.')
            ->setMetaTitle('Essaouira medina riads')
            ->setMetaDescription('Curated riads in Essaouira medina.');
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/web-pages/%s?locale=en',
            $project->getId(),
            $page->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('en', $payload['locale'] ?? null);
        self::assertSame('en', $payload['requestedLocale'] ?? null);
        self::assertFalse($payload['fallback'] ?? true);
        self::assertSame('Medina riads in Essaouira', $payload['headline'] ?? null);
    }

    public function testEnabledLocaleWithoutTranslationFallsBackExplicitly(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'viewer.mogador@grainesdigitales.local');
        $project = $this->findProjectBySlug('blue-bay-mogador');
        $page = $this->findWebPageByProject($project);

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/web-pages/%s?locale=es',
            $project->getId(),
            $page->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame('es', $payload['requestedLocale'] ?? null);
        self::assertSame('fr', $payload['locale'] ?? null);
        self::assertSame('fr', $payload['fallbackLocale'] ?? null);
        self::assertTrue($payload['fallback'] ?? false);
    }

    public function testTranslationFromAnotherProjectIsHidden(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'viewer.mogador@grainesdigitales.local');
        $riadProject = $this->findProjectBySlug('riads-medina-essaouira');
        $coastalProject = $this->findProjectBySlug('blue-bay-mogador');
        $riadPage = $this->findWebPageByProject($riadProject);

        $client->request('GET', sprintf(
            '/api/projects/%s/web-pages/%s/translations/fr',
            $coastalProject->getId(),
            $riadPage->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
