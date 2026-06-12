<?php

namespace App\Tests\Functional;

final class AccommodationHasPartTest extends FunctionalTestCase
{
    private const EDITOR = 'editor.essaouira@grainesdigitales.local';
    private const IDENTIFIER = 'BAVLC001';
    private const PUT_FR_ROUTE = '/api/projects/%s/accommodations/%s?locale=fr';

    protected function shouldResetDatabaseBeforeEachTest(): bool
    {
        return true;
    }

    public function testPutHasPartPersistsComponentsViaIdentifierRoute(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $response = $client->request('PUT', sprintf(
            self::PUT_FR_ROUTE,
            $project->getId(),
            self::IDENTIFIER,
        ), [
            'headers' => $this->jsonHeaders($token),
            'json' => [
                'hasPart' => [
                    [
                        'additionalType' => 'SCREEN_ACCOMMODATION_FULL',
                        'name' => 'Intro',
                        'headline' => 'Bienvenue',
                        'text' => 'Un texte de présentation.',
                        'associatedMedia' => [],
                        'meta' => ['reverse' => true, 'overlayMode' => 'light'],
                    ],
                    [
                        'additionalType' => 'SCREEN_ACCOMMODATION_DUO',
                        'headline' => 'Deux espaces complémentaires',
                    ],
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertCount(2, $payload['hasPart'] ?? []);

        $first = $payload['hasPart'][0];
        self::assertSame('SCREEN_ACCOMMODATION_FULL', $first['additionalType']);
        self::assertSame('Bienvenue', $first['headline']);
        self::assertSame('Un texte de présentation.', $first['text']);
        self::assertSame('Intro', $first['name']);
        self::assertSame(0, $first['position']);
        self::assertTrue($first['meta']['reverse']);
        self::assertSame('light', $first['meta']['overlayMode']);

        $second = $payload['hasPart'][1];
        self::assertSame('SCREEN_ACCOMMODATION_DUO', $second['additionalType']);
        self::assertSame('Deux espaces complémentaires', $second['headline']);
        self::assertSame(1, $second['position']);
    }

    public function testHasPartReadFallsBackToSourceLocale(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('PUT', sprintf(
            self::PUT_FR_ROUTE,
            $project->getId(),
            self::IDENTIFIER,
        ), [
            'headers' => $this->jsonHeaders($token),
            'json' => [
                'hasPart' => [
                    [
                        'additionalType' => 'SCREEN_ACCOMMODATION_FULL',
                        'headline' => 'Bienvenue',
                        'text' => 'Texte FR',
                    ],
                ],
            ],
        ]);
        self::assertResponseIsSuccessful();

        $accommodation = $this->findAccommodationByIdentifier(self::IDENTIFIER, $project);

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/accommodations/%s?locale=es',
            $project->getId(),
            $accommodation->getId(),
        ), ['headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertCount(1, $payload['hasPart'] ?? []);
        // No Spanish translation: the component falls back to the source-locale (fr) text.
        self::assertSame('Bienvenue', $payload['hasPart'][0]['headline']);
        self::assertSame('Texte FR', $payload['hasPart'][0]['text']);
    }

    public function testHasPartRejectsUnknownAdditionalType(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::EDITOR);
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('PUT', sprintf(
            self::PUT_FR_ROUTE,
            $project->getId(),
            self::IDENTIFIER,
        ), [
            'headers' => $this->jsonHeaders($token),
            'json' => [
                'hasPart' => [
                    ['additionalType' => 'SCREEN_UNKNOWN', 'headline' => 'X'],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    /** @return array<string, string> */
    private function jsonHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ];
    }
}
