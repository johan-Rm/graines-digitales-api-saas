<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

final class ProjectIsolationTest extends ApiTestCase
{
    public function testMediaObjectFromAnotherProjectIsHidden(): void
    {
        self::markTestIncomplete('Create fixtures for two projects and assert 403 or 404 on cross-project media access.');
    }

    public function testSoftDeletedContentIsHiddenFromCollections(): void
    {
        self::markTestIncomplete('Create a deletedAt fixture and assert it is absent from project collections.');
    }
}
