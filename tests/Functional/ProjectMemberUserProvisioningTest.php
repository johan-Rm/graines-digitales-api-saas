<?php

namespace App\Tests\Functional;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\ProjectRole;

final class ProjectMemberUserProvisioningTest extends FunctionalTestCase
{
    public function testProjectMemberRouteCreatesMembershipForOrganizationMember(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'new.owner@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $user = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'new.owner@example.test',
        ]);
        self::assertInstanceOf(User::class, $user);
        $this->addOrganizationMembershipForProject($project, $user);

        $response = $client->request('POST', sprintf('/api/projects/%s/members', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'new.owner@example.test',
                'projectRole' => 'owner',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $response->toArray(false);

        self::assertSame('new.owner@example.test', $payload['user']['email'] ?? null);
        self::assertFalse($payload['user']['created'] ?? null);
        self::assertSame('owner', $payload['membership']['role'] ?? null);
        self::assertTrue($payload['membership']['created'] ?? null);

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
                'name' => 'Provisioning Test Organization',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $organizationPayload = $organizationResponse->toArray(false);

        $projectResponse = $client->request('POST', '/api/projects', [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'organization' => '/api/organizations/'.$organizationPayload['id'],
                'name' => 'Provisioning Test Project',
                'sourceLocale' => 'fr',
                'enabledLocales' => ['fr', 'en'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $projectPayload = $projectResponse->toArray(false);
        self::assertSame('provisioning-test-project', $projectPayload['slug'] ?? null);

        $admin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);
        self::assertInstanceOf(User::class, $admin);

        $creatorMembership = $this->entityManager()->getRepository(ProjectMember::class)->findOneBy([
            'project' => $this->findProjectBySlug('provisioning-test-project'),
            'user' => $admin,
        ]);

        self::assertInstanceOf(ProjectMember::class, $creatorMembership);
        self::assertSame(ProjectRole::Owner, $creatorMembership->getRole());

        $member = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'johan.remy@graines-digitales.online',
        ]);
        self::assertInstanceOf(User::class, $member);
        $this->addOrganizationMembershipForProject(
            $this->findProjectBySlug('provisioning-test-project'),
            $member,
        );

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

        $user = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'existing.member@example.test',
        ]);
        self::assertInstanceOf(User::class, $user);
        $this->addOrganizationMembershipForProject($project, $user);

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

    public function testProjectMemberRouteRequiresAnExistingOrganizationMember(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');

        $client->request('POST', '/api/auth/local-users', [
            'json' => [
                'email' => 'not.organization.member@example.test',
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', sprintf('/api/projects/%s/members', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'json' => [
                'email' => 'not.organization.member@example.test',
                'projectRole' => 'admin',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    private function addOrganizationMembershipForProject(
        Project $project,
        User $user,
        OrganizationRole $role = OrganizationRole::Member,
    ): void {
        $managedProject = $this->entityManager()->getRepository(Project::class)->find($project->getId());
        $managedUser = $this->entityManager()->getRepository(User::class)->find($user->getId());

        self::assertInstanceOf(Project::class, $managedProject);
        self::assertInstanceOf(User::class, $managedUser);

        $organization = $managedProject->getOrganization();
        self::assertInstanceOf(Organization::class, $organization);

        $membership = $this->entityManager()->getRepository(OrganizationMember::class)->findOneBy([
            'organization' => $organization,
            'user' => $managedUser,
        ]);

        if (!$membership instanceof OrganizationMember) {
            $membership = new OrganizationMember();
            $membership->setOrganization($organization);
            $membership->setUser($managedUser);
            $membership->markCreatedBy($managedUser);
            $this->entityManager()->persist($membership);
        }

        $membership->setRole($role);
        $membership->touch($managedUser);
        $this->entityManager()->flush();
    }
}
