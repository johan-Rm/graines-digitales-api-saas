<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\WebPage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class ProjectIsolationTest extends ApiTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->recreateSchema();
        $this->loadDemoFixtures();
        self::ensureKernelShutdown();
    }

    public function testProjectScopedResourceFromAnotherProjectIsHidden(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'editor.essaouira@grainesdigitales.local');
        $riadProject = $this->findProjectBySlug('riads-medina-essaouira');
        $coastalProject = $this->findProjectBySlug('blue-bay-mogador');
        $riadPage = $this->findWebPageByProject($riadProject);

        $client->request('GET', sprintf(
            '/api/projects/%s/web-pages/%s',
            $coastalProject->getId(),
            $riadPage->getId(),
        ), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testSoftDeletedContentIsHiddenFromCollections(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin@grainesdigitales.local');
        $project = $this->findProjectBySlug('riads-medina-essaouira');
        $page = $this->findWebPageByProject($project);
        $pageId = (string) $page->getId();
        $admin = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);

        self::assertInstanceOf(User::class, $admin);
        $page->archive($admin);
        $this->entityManager()->flush();

        $response = $client->request('GET', sprintf('/api/projects/%s/web-pages', $project->getId()), [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);
        $items = $payload['member'] ?? $payload;

        self::assertIsArray($items);
        self::assertNotContains($pageId, array_map(
            static fn (array $item): ?string => $item['id'] ?? null,
            $items,
        ));
    }

    private function recreateSchema(): void
    {
        $entityManager = $this->entityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function loadDemoFixtures(): void
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $application->run(new ArrayInput([
            'command' => 'app:fixtures:load-demo',
        ]), new NullOutput());
    }

    private function login(object $client, string $email): string
    {
        $response = $client->request('POST', '/api/auth/login', [
            'json' => [
                'email' => $email,
                'password' => 'password123',
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertIsString($payload['token'] ?? null);

        return $payload['token'];
    }

    private function findProjectBySlug(string $slug): Project
    {
        $project = $this->entityManager()->getRepository(Project::class)->findOneBy(['slug' => $slug]);

        self::assertInstanceOf(Project::class, $project);

        return $project;
    }

    private function findWebPageByProject(Project $project): WebPage
    {
        $page = $this->entityManager()->getRepository(WebPage::class)->findOneBy([
            'project' => $project,
            'deletedAt' => null,
        ]);

        self::assertInstanceOf(WebPage::class, $page);

        return $page;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
