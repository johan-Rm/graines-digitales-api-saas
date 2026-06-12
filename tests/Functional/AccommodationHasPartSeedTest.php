<?php

namespace App\Tests\Functional;

/**
 * Verifies that hasPart content blocks created through the fixture/seed flow are exposed
 * in the standard Accommodation GET response (the seed pipeline mirrors this fixture path).
 */
final class AccommodationHasPartSeedTest extends FunctionalTestCase
{
    private const ADMIN = 'admin@grainesdigitales.local';

    public function testSeededAccommodationExposesHasPart(): void
    {
        $client = static::createClient();
        $token = $this->login($client, self::ADMIN);
        $project = $this->findProjectBySlug('blue-bay-mogador');
        $accommodation = $this->findAccommodationByIdentifier('LV001', $project);

        $response = $client->request('GET', sprintf(
            '/api/projects/%s/accommodations/%s',
            $project->getId(),
            $accommodation->getId(),
        ), ['headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertCount(2, $payload['hasPart'] ?? []);

        $first = $payload['hasPart'][0];
        self::assertSame('SCREEN_ACCOMMODATION_FULL', $first['additionalType']);
        self::assertSame('Une villa face au lagon', $first['headline']);
        self::assertSame("Vue d'ensemble", $first['name']);
        self::assertSame(1, $first['position']);
        self::assertSame('light', $first['meta']['overlayMode']);
        self::assertCount(1, $first['associatedMedia']);

        $second = $payload['hasPart'][1];
        self::assertSame('SCREEN_ACCOMMODATION_DUO', $second['additionalType']);
        self::assertSame('Deux espaces, une même vue', $second['headline']);
        self::assertSame(2, $second['position']);
    }
}
