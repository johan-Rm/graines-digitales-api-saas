<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

final class AuthenticationTest extends ApiTestCase
{
    public function testProjectRoutesRequireJwt(): void
    {
        static::createClient()->request('GET', '/api/projects/018f0000-0000-7000-8000-000000000001/media-objects');

        self::assertResponseStatusCodeSame(401);
    }
}
