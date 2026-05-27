<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectCollectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->recreateSchema();
        $this->seedSuperAdmin();
        self::ensureKernelShutdown();
    }

    public function testSuperAdminSeesAllProjects(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        [$org1Id, $org2Id] = $this->createTwoOrgs($client, $superAdminToken);
        $this->createProject($client, $superAdminToken, $org1Id, 'Project Alpha');
        $this->createProject($client, $superAdminToken, $org2Id, 'Project Beta');

        $response = $client->request('GET', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(2, $response->toArray(false)['totalItems'] ?? null);
    }

    public function testOrgOwnerSeesOnlyOwnOrganizationProjects(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        [$org1Id, $org2Id] = $this->createTwoOrgs($client, $superAdminToken);
        $this->createProject($client, $superAdminToken, $org1Id, 'Project Alpha');
        $this->createProject($client, $superAdminToken, $org2Id, 'Project Beta');

        $client->request('POST', sprintf('/api/organizations/%s/members', $org1Id), [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
            'json' => [
                'email' => 'owner-alpha@example.test',
                'password' => 'password123',
                'role' => 'owner',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $ownerToken = $this->login($client, 'owner-alpha@example.test');

        $response = $client->request('GET', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$ownerToken],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertSame(1, $payload['totalItems'] ?? null);
        self::assertStringContainsString($org1Id, $payload['member'][0]['organization'] ?? '');
    }

    public function testMemberOfNoOrganizationSeesNoProjects(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        [$org1Id, $org2Id] = $this->createTwoOrgs($client, $superAdminToken);
        $this->createProject($client, $superAdminToken, $org1Id, 'Project Alpha');
        $this->createProject($client, $superAdminToken, $org2Id, 'Project Beta');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'orphan@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $orphanToken = $this->login($client, 'orphan@example.test');

        $response = $client->request('GET', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$orphanToken],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $response->toArray(false)['totalItems'] ?? null);
    }

    public function testMemberOfOrgADoesNotSeeProjectsFromOrgB(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        [$org1Id, $org2Id] = $this->createTwoOrgs($client, $superAdminToken);
        $this->createProject($client, $superAdminToken, $org1Id, 'Project Alpha');
        $this->createProject($client, $superAdminToken, $org2Id, 'Project Beta');

        $client->request('POST', sprintf('/api/organizations/%s/members', $org1Id), [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
            'json' => [
                'email' => 'member-a@example.test',
                'password' => 'password123',
                'role' => 'member',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $memberToken = $this->login($client, 'member-a@example.test');

        $response = $client->request('GET', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$memberToken],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertSame(1, $payload['totalItems'] ?? null);

        foreach ($payload['member'] ?? [] as $project) {
            self::assertStringNotContainsString($org2Id, $project['organization'] ?? '');
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createTwoOrgs(object $client, string $token): array
    {
        $r1 = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => ['name' => 'Organization Alpha'],
        ]);
        self::assertResponseStatusCodeSame(201);

        $r2 = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => ['name' => 'Organization Beta'],
        ]);
        self::assertResponseStatusCodeSame(201);

        return [$r1->toArray(false)['id'], $r2->toArray(false)['id']];
    }

    private function createProject(object $client, string $token, string $orgId, string $name): void
    {
        $client->request('POST', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'organization' => '/api/organizations/'.$orgId,
                'name' => $name,
                'sourceLocale' => 'fr',
                'enabledLocales' => ['fr'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
    }

    private function seedSuperAdmin(): void
    {
        $existing = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);

        if ($existing instanceof User) {
            return;
        }

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = new User();
        $admin->setEmail('admin@grainesdigitales.local');
        $admin->setRoles(['ROLE_USER']);
        $admin->setPassword($hasher->hashPassword($admin, 'password123'));
        $this->entityManager()->persist($admin);
        $this->entityManager()->flush();
    }
}
