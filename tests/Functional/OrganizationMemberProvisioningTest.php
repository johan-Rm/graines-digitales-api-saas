<?php

namespace App\Tests\Functional;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrganizationRole;

final class OrganizationMemberProvisioningTest extends FunctionalTestCase
{
    // --- GET /api/organizations/{id}/members ---

    public function testSuperAdminCanListOrganizationMembers(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $response = $client->request('GET', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $members = $response->toArray(false);
        self::assertIsArray($members);
        self::assertNotEmpty($members);
        self::assertArrayHasKey('role', $members[0]);
        self::assertArrayHasKey('user', $members[0]);
        self::assertArrayNotHasKey('password', $members[0]['user']);
    }

    public function testOrganizationOwnerCanListMembers(): void
    {
        $client = static::createClient();

        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');
        $orgAdmin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);
        self::assertInstanceOf(User::class, $orgAdmin);
        self::assertNotNull($this->entityManager()->getRepository(OrganizationMember::class)->findOneBy([
            'organization' => $organization,
            'user' => $orgAdmin,
        ]));

        $token = $this->login($client, 'admin@grainesdigitales.local');

        $response = $client->request('GET', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNonMemberCannotListOrganizationMembers(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'outsider@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $token = $this->login($client, 'outsider@example.test');
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $client->request('GET', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // --- POST /api/organizations/{id}/members ---

    public function testSuperAdminCanAddMemberToOrganization(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $response = $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'new.member@example.test',
                'password' => 'password123',
                'role' => 'member',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertSame('new.member@example.test', $payload['user']['email'] ?? null);
        self::assertTrue($payload['user']['created'] ?? null);
        self::assertSame('member', $payload['membership']['role'] ?? null);
        self::assertTrue($payload['membership']['created'] ?? null);
        self::assertArrayNotHasKey('password', $payload['user']);

        $user = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'new.member@example.test',
        ]);
        self::assertInstanceOf(User::class, $user);
        self::assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testOrganizationOwnerCanAddMember(): void
    {
        $client = static::createClient();
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $ownerToken = $this->login($client, 'admin@grainesdigitales.local');

        $response = $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$ownerToken],
            'json' => [
                'email' => 'org.member.by.owner@example.test',
                'password' => 'password123',
                'role' => 'admin',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testOrganizationMemberCannotAddOtherMembers(): void
    {
        $client = static::createClient();
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'simple.member@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $member = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'simple.member@example.test',
        ]);
        self::assertInstanceOf(User::class, $member);

        $orgMember = new OrganizationMember();
        $orgMember->setOrganization($organization);
        $orgMember->setUser($member);
        $orgMember->setRole(OrganizationRole::Member);
        $orgMember->markCreatedBy($member);
        $orgMember->touch($member);
        $this->entityManager()->persist($orgMember);
        $this->entityManager()->flush();

        $token = $this->login($client, 'simple.member@example.test');

        $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'should.fail@example.test',
                'password' => 'password123',
                'role' => 'member',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testBillingMemberCannotAddOtherMembers(): void
    {
        $client = static::createClient();
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'billing.member@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $billingUser = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'billing.member@example.test',
        ]);
        self::assertInstanceOf(User::class, $billingUser);

        $orgMember = new OrganizationMember();
        $orgMember->setOrganization($organization);
        $orgMember->setUser($billingUser);
        $orgMember->setRole(OrganizationRole::Billing);
        $orgMember->markCreatedBy($billingUser);
        $orgMember->touch($billingUser);
        $this->entityManager()->persist($orgMember);
        $this->entityManager()->flush();

        $token = $this->login($client, 'billing.member@example.test');

        $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'should.fail.billing@example.test',
                'password' => 'password123',
                'role' => 'member',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberOfOtherOrganizationCannotAddMembers(): void
    {
        $client = static::createClient();
        $targetOrganization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');
        $otherOrganization = $this->findOrganizationBySlug('atlas-coastal-living');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'crossorg.admin@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $crossOrgUser = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'crossorg.admin@example.test',
        ]);
        self::assertInstanceOf(User::class, $crossOrgUser);

        $orgMember = new OrganizationMember();
        $orgMember->setOrganization($otherOrganization);
        $orgMember->setUser($crossOrgUser);
        $orgMember->setRole(OrganizationRole::Admin);
        $orgMember->markCreatedBy($crossOrgUser);
        $orgMember->touch($crossOrgUser);
        $this->entityManager()->persist($orgMember);
        $this->entityManager()->flush();

        $token = $this->login($client, 'crossorg.admin@example.test');

        $client->request('POST', sprintf('/api/organizations/%s/members', $targetOrganization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'injected@example.test',
                'password' => 'password123',
                'role' => 'member',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCannotGrantSuperAdminRoleViaPayload(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'elevation.attempt@example.test',
                'password' => 'password123',
                'role' => 'ROLE_SUPER_ADMIN',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testExistingUserCanBeLinkedToOrganizationWithoutPassword(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $existingUser = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'editor.essaouira@grainesdigitales.local',
        ]);
        self::assertInstanceOf(User::class, $existingUser);

        $response = $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'editor.essaouira@grainesdigitales.local',
                'role' => 'admin',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        self::assertFalse($payload['user']['created'] ?? null);
        self::assertSame('admin', $payload['membership']['role'] ?? null);
    }

    public function testNewUserRequiresPasswordWhenNotExisting(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'no.password.new@example.test',
                'role' => 'member',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testDuplicateMembershipIsIdempotentAndUpdatesRole(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $organization = $this->findOrganizationBySlug('essaouira-patrimoine-immobilier');

        $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'idempotent.member@example.test',
                'password' => 'password123',
                'role' => 'member',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $response = $client->request('POST', sprintf('/api/organizations/%s/members', $organization->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'idempotent.member@example.test',
                'role' => 'admin',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $payload = $response->toArray(false);
        self::assertFalse($payload['membership']['created'] ?? null);
        self::assertSame('admin', $payload['membership']['role'] ?? null);
    }

    private function findOrganizationBySlug(string $slug): Organization
    {
        $organization = $this->entityManager()->getRepository(Organization::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Organization::class, $organization);

        return $organization;
    }
}
