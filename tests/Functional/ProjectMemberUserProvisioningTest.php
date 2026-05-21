<?php

namespace App\Tests\Functional;

use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;

final class ProjectMemberUserProvisioningTest extends FunctionalTestCase
{
    public function testProjectMemberRouteCreatesUserAndMembership(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $response = $client->request('POST', sprintf('/api/projects/%s/members', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'new.owner@example.test',
                'password' => 'password123',
                'projectRole' => 'owner',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertSame('new.owner@example.test', $payload['user']['email'] ?? null);
        self::assertTrue($payload['user']['created'] ?? null);
        self::assertSame('owner', $payload['membership']['role'] ?? null);
        self::assertTrue($payload['membership']['created'] ?? null);

        $user = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'new.owner@example.test',
        ]);
        self::assertInstanceOf(User::class, $user);
        self::assertNotSame('password123', $user->getPassword());

        $membership = $this->entityManager()->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);
        self::assertInstanceOf(ProjectMember::class, $membership);
        self::assertSame(ProjectRole::Owner, $membership->getRole());
    }

    public function testProjectCreatorCanImmediatelyProvisionProjectMembers(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');

        $organizationResponse = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'name' => 'MLK - My Little Kasbah',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $organizationPayload = $organizationResponse->toArray(false);

        $projectResponse = $client->request('POST', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'organization' => '/api/organizations/'.$organizationPayload['id'],
                'name' => 'MLK - Modern Site Web',
                'sourceLocale' => 'fr',
                'enabledLocales' => ['fr', 'en'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $projectPayload = $projectResponse->toArray(false);
        self::assertSame('mlk-modern-site-web', $projectPayload['slug'] ?? null);

        $admin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);
        self::assertInstanceOf(User::class, $admin);

        $creatorMembership = $this->entityManager()->getRepository(ProjectMember::class)->findOneBy([
            'project' => $this->findProjectBySlug('mlk-modern-site-web'),
            'user' => $admin,
        ]);

        self::assertInstanceOf(ProjectMember::class, $creatorMembership);
        self::assertSame(ProjectRole::Owner, $creatorMembership->getRole());

        $memberResponse = $client->request('POST', sprintf('/api/projects/%s/members', $projectPayload['id']), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'johan.remy@graines-digitales.online',
                'password' => 'password123',
                'projectRole' => 'owner',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $memberPayload = $memberResponse->toArray(false);

        self::assertSame('johan.remy@graines-digitales.online', $memberPayload['user']['email'] ?? null);
        self::assertSame('owner', $memberPayload['membership']['role'] ?? null);
    }

    public function testProjectMemberRouteAssociatesExistingUserAndCanUpdateRole(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('villas-arganier-ghazoua');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'existing.member@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $response = $client->request('POST', sprintf('/api/projects/%s/members', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'existing.member@example.test',
                'projectRole' => 'admin',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertFalse($payload['user']['created'] ?? null);
        self::assertTrue($payload['membership']['created'] ?? null);
        self::assertSame('admin', $payload['membership']['role'] ?? null);

        $secondResponse = $client->request('POST', sprintf('/api/projects/%s/members', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'existing.member@example.test',
                'projectRole' => 'viewer',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(200, $secondResponse->getStatusCode());
        $secondPayload = $secondResponse->toArray(false);

        self::assertFalse($secondPayload['membership']['created'] ?? null);
        self::assertSame('viewer', $secondPayload['membership']['role'] ?? null);
    }

    public function testProjectMemberRouteRequiresPasswordForNewUser(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $client->request('POST', sprintf('/api/projects/%s/members', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'missing.password@example.test',
                'projectRole' => 'admin',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}
