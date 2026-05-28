<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OrganizationCollectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->recreateSchema();
        $this->seedSuperAdmin();
        self::ensureKernelShutdown();
    }

    public function testSuperAdminSeesAllOrganizations(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        $this->createOrg($client, $superAdminToken, 'Organization Alpha');
        $this->createOrg($client, $superAdminToken, 'Organization Beta');

        $response = $client->request('GET', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(2, $response->toArray(false)['totalItems'] ?? null);
    }

    public function testMemberSeesOnlyOwnOrganizations(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        $org1Id = $this->createOrg($client, $superAdminToken, 'Organization Alpha');
        $this->createOrg($client, $superAdminToken, 'Organization Beta');

        $client->request('POST', sprintf('/api/organizations/%s/members', $org1Id), [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
            'json' => [
                'email' => 'member-alpha@example.test',
                'password' => 'password123',
                'role' => 'member',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $memberToken = $this->login($client, 'member-alpha@example.test');

        $response = $client->request('GET', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$memberToken],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertSame(1, $payload['totalItems'] ?? null);
        self::assertSame($org1Id, $payload['member'][0]['id'] ?? null);
    }

    public function testMemberOfNoOrganizationSeesEmptyList(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        $this->createOrg($client, $superAdminToken, 'Organization Alpha');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'orphan@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $orphanToken = $this->login($client, 'orphan@example.test');

        $response = $client->request('GET', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$orphanToken],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $response->toArray(false)['totalItems'] ?? null);
    }

    public function testMemberOfOrgADoesNotSeeOrgB(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        $org1Id = $this->createOrg($client, $superAdminToken, 'Organization Alpha');
        $org2Id = $this->createOrg($client, $superAdminToken, 'Organization Beta');

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

        $response = $client->request('GET', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$memberToken],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertSame(1, $payload['totalItems'] ?? null);

        $returnedIds = array_map(static fn (array $org): string => $org['id'] ?? '', $payload['member'] ?? []);
        self::assertNotContains($org2Id, $returnedIds);
    }

    private function createOrg(object $client, string $token, string $name): string
    {
        $response = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => ['name' => $name],
        ]);
        self::assertResponseStatusCodeSame(201);

        return $response->toArray(false)['id'];
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
