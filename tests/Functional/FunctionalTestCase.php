<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Accommodation;
use App\Entity\Article;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\WebPage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class FunctionalTestCase extends ApiTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->recreateSchema();
        $this->loadDemoFixtures();
        self::ensureKernelShutdown();
    }

    protected function login(object $client, string $email): string
    {
        $payload = $this->loginPayload($client, $email);

        self::assertIsString($payload['token'] ?? null);

        return $payload['token'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginPayload(object $client, string $email): array
    {
        $response = $client->request('POST', '/api/auth/login', [
            'json' => [
                'email' => $email,
                'password' => 'password123',
            ],
        ]);

        self::assertResponseIsSuccessful();

        return $response->toArray(false);
    }

    protected function findProjectBySlug(string $slug): Project
    {
        $project = $this->entityManager()->getRepository(Project::class)->findOneBy(['slug' => $slug]);

        self::assertInstanceOf(Project::class, $project);

        return $project;
    }

    protected function findWebPageByProject(Project $project): WebPage
    {
        $page = $this->entityManager()->getRepository(WebPage::class)->findOneBy([
            'project' => $project,
            'deletedAt' => null,
        ]);

        self::assertInstanceOf(WebPage::class, $page);

        return $page;
    }

    protected function findArticleByProject(Project $project): Article
    {
        $article = $this->entityManager()->getRepository(Article::class)->findOneBy([
            'project' => $project,
            'deletedAt' => null,
        ]);

        self::assertInstanceOf(Article::class, $article);

        return $article;
    }

    protected function findAccommodationByIdentifier(string $identifier, ?Project $project = null): Accommodation
    {
        $criteria = [
            'identifier' => $identifier,
            'deletedAt' => null,
        ];

        if ($project instanceof Project) {
            $criteria['project'] = $project;
        }

        $accommodation = $this->entityManager()->getRepository(Accommodation::class)->findOneBy($criteria);

        self::assertInstanceOf(Accommodation::class, $accommodation);

        return $accommodation;
    }

    protected function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
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
            '--with-test-tenants' => true,
        ]), new NullOutput());
    }
}
