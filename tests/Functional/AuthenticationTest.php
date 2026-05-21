<?php

namespace App\Tests\Functional;

final class AuthenticationTest extends FunctionalTestCase
{
    public function testProjectRoutesRequireJwt(): void
    {
        static::createClient()->request('GET', '/api/projects/018f0000-0000-7000-8000-000000000001/media-objects');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLocalUserCreationRejectsInvalidPayload(): void
    {
        static::createClient()->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'not-an-email',
                'password' => 'short',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testLoginReturnsJwtAndRefreshToken(): void
    {
        $payload = $this->loginPayload(static::createClient(), 'admin@grainesdigitales.local');

        self::assertIsString($payload['token'] ?? null);
        self::assertIsString($this->extractRefreshToken($payload));
    }

    public function testRefreshTokenReturnsNewJwt(): void
    {
        $client = static::createClient();
        $loginPayload = $this->loginPayload($client, 'admin@grainesdigitales.local');

        $response = $client->request('POST', '/api/auth/refresh', [
            'json' => [
                'refreshToken' => $this->extractRefreshToken($loginPayload),
            ],
        ]);

        self::assertResponseIsSuccessful();
        $refreshPayload = $response->toArray(false);

        self::assertIsString($refreshPayload['token'] ?? null);
    }

    public function testInvalidRefreshTokenIsRejected(): void
    {
        static::createClient()->request('POST', '/api/auth/refresh', [
            'json' => [
                'refreshToken' => 'invalid-refresh-token',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLocalUserCreationCreatesHashedUserAndRejectsDuplicate(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'new.admin@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertSame('new.admin@example.test', $payload['email'] ?? null);
        self::assertContains('ROLE_ADMIN', $payload['roles'] ?? []);

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'new.admin@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractRefreshToken(array $payload): ?string
    {
        $refreshToken = $payload['refreshToken'] ?? $payload['refresh_token'] ?? null;

        return is_string($refreshToken) ? $refreshToken : null;
    }
}
