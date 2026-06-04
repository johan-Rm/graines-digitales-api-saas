<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Accommodation;
use App\Entity\Article;
use App\Entity\MediaObject;
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
    private static ?string $databasePreparedForClass = null;

    protected function setUp(): void
    {
        self::bootKernel();
        if ($this->shouldResetDatabaseBeforeEachTest() || self::$databasePreparedForClass !== static::class) {
            $this->recreateSchema();
            $this->loadTestFixtures();
            self::$databasePreparedForClass = static::class;
        }
        self::ensureKernelShutdown();
    }

    protected function shouldResetDatabaseBeforeEachTest(): bool
    {
        return false;
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
        ]);

        self::assertInstanceOf(WebPage::class, $page);

        return $page;
    }

    protected function findArticleByProject(Project $project): Article
    {
        $article = $this->entityManager()->getRepository(Article::class)->findOneBy([
            'project' => $project,
        ]);

        self::assertInstanceOf(Article::class, $article);

        return $article;
    }

    protected function findMediaObjectByProject(Project $project): MediaObject
    {
        $mediaObject = $this->entityManager()->getRepository(MediaObject::class)->findOneBy([
            'project' => $project,
        ]);

        self::assertInstanceOf(MediaObject::class, $mediaObject);

        return $mediaObject;
    }

    protected function findAccommodationByIdentifier(string $identifier, ?Project $project = null): Accommodation
    {
        $criteria = [
            'identifier' => $identifier,
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

    /**
     * Asserts a "with translations" payload exposes the full translations collection but
     * never flattens a single locale at the root: per-locale content fields and the
     * locale-resolution metadata must live inside `translations`, not at the root.
     *
     * @param array<string, mixed> $payload
     */
    protected function assertNoLocalizedRootFields(array $payload): void
    {
        self::assertArrayHasKey('translations', $payload, '/translations responses must expose the translations collection');

        $forbiddenRootKeys = [
            'slug', 'name', 'label', 'highlight', 'body', 'review', 'metaTitle', 'metaDescription',
            'text', 'headline', 'caption', 'locale', 'requestedLocale', 'fallback', 'fallbackLocale',
        ];

        foreach ($forbiddenRootKeys as $key) {
            self::assertArrayNotHasKey($key, $payload, sprintf('/translations must not flatten "%s" at the root', $key));
        }
    }

    protected function recreateSchema(): void
    {
        $entityManager = $this->entityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function loadTestFixtures(): void
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitCode = $application->run(new ArrayInput([
            'command' => 'app:fixtures:load-test',
        ]), $output);
        if (0 !== $exitCode) {
            throw new \RuntimeException('app:fixtures:load-test failed: '.$output->fetch());
        }
    }
}
