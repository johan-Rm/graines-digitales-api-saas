<?php

namespace App\Tests\Functional;

use App\Entity\CategoryCode;

final class CollectionPaginationAndLoginThrottleTest extends FunctionalTestCase
{
    public function testSecondCollectionPageRemainsScopedToProject(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/ld+json'];
        $url = sprintf('/api/projects/%s/category-codes', $project->getId());
        $first = $client->request('GET', $url, ['headers' => $headers])->toArray(false);
        self::assertResponseIsSuccessful();
        $count = $this->entityManager()->getRepository(CategoryCode::class)->count(['project' => $project]);
        self::assertSame($count, $first['totalItems']);
        $second = $client->request('GET', $url.'?page=2', ['headers' => $headers])->toArray(false);
        self::assertResponseIsSuccessful();
        self::assertSame($count, $second['totalItems']);
        self::assertSame([], $second['member']);
    }

    public function testRepeatedInvalidLoginIsThrottled(): void
    {
        $client = static::createClient();
        $email = 'throttle-'.bin2hex(random_bytes(8)).'@example.test';
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $client->request('POST', '/api/auth/login', ['json' => [
                'email' => $email,
                'password' => 'invalid-password',
            ]]);
            self::assertResponseStatusCodeSame(401);
        }
        $response = $client->request('POST', '/api/auth/login', ['json' => [
            'email' => $email,
            'password' => 'invalid-password',
        ]]);
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('Too many failed login attempts', $response->toArray(false)['message']);
    }
}
