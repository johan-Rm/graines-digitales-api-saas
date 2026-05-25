<?php

namespace App\Tests\Functional;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\ProjectRole;

final class PlatformAuthorizationTest extends FunctionalTestCase
{
    public function testOnlyConfiguredSuperAdminCanCreateOrganization(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        $response = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
            'json' => [
                'name' => 'Platform Created Organization',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('platform-created-organization', $response->toArray(false)['slug'] ?? null);

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'regular.admin@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $regularAdminToken = $this->login($client, 'regular.admin@example.test');

        $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$regularAdminToken],
            'json' => [
                'name' => 'Rejected Organization',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCannotBeGrantedFromPublicPayload(): void
    {
        static::createClient()->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'payload.superadmin@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_SUPER_ADMIN'],
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testOrganizationAdminCanCreateProjectInOwnOrganization(): void
    {
        $client = static::createClient();
        $superAdminToken = $this->login($client, 'admin@grainesdigitales.local');

        $organizationResponse = $client->request('POST', '/api/organizations', [
            'headers' => ['Authorization' => 'Bearer '.$superAdminToken],
            'json' => [
                'name' => 'Organization Admin Project Scope',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $organizationPayload = $organizationResponse->toArray(false);

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'org.admin@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $organization = $this->entityManager()->getRepository(Organization::class)->findOneBy([
            'slug' => 'organization-admin-project-scope',
        ]);
        $user = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'org.admin@example.test',
        ]);
        self::assertInstanceOf(Organization::class, $organization);
        self::assertInstanceOf(User::class, $user);

        $organizationMember = new OrganizationMember();
        $organizationMember->setOrganization($organization);
        $organizationMember->setUser($user);
        $organizationMember->setRole(OrganizationRole::Admin);
        $organizationMember->markCreatedBy($user);
        $organizationMember->touch($user);
        $this->entityManager()->persist($organizationMember);
        $this->entityManager()->flush();

        $organizationAdminToken = $this->login($client, 'org.admin@example.test');

        $projectResponse = $client->request('POST', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$organizationAdminToken],
            'json' => [
                'organization' => '/api/organizations/'.$organizationPayload['id'],
                'name' => 'Project From Organization Admin',
                'sourceLocale' => 'fr',
                'enabledLocales' => ['fr', 'en'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $membership = $this->entityManager()->getRepository(ProjectMember::class)->findOneBy([
            'project' => $this->findProjectBySlug('project-from-organization-admin'),
            'user' => $user,
        ]);

        self::assertInstanceOf(ProjectMember::class, $membership);
        self::assertSame(ProjectRole::Owner, $membership->getRole());
    }

    public function testEditorCannotManageProjectMembers(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $client->request('POST', sprintf('/api/projects/%s/members', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'editor-created-member@example.test',
                'password' => 'password123',
                'projectRole' => 'viewer',
                'roles' => ['ROLE_USER'],
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
