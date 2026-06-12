<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BootstrapManualCommandTest extends FunctionalTestCase
{
    protected function shouldResetDatabaseBeforeEachTest(): bool
    {
        return true;
    }

    public function testProjectScopedPasswordUpdatesKeepExpectedBootstrapPasswords(): void
    {
        $tester = $this->makeCommandTester();

        $tester->execute([
            '--password' => 'super-admin-password',
        ], ['decorated' => false]);
        self::assertSame(0, $tester->getStatusCode());

        $tester->execute([
            '--password' => 'blue-bay-password',
            '--password-project' => 'blue-bay-mogador',
        ], ['decorated' => false]);
        self::assertSame(0, $tester->getStatusCode());

        $tester->execute([
            '--password' => 'mlk-password',
            '--password-project' => 'mlk-modern-site-web',
        ], ['decorated' => false]);
        self::assertSame(0, $tester->getStatusCode());

        $this->entityManager()->clear();

        $this->assertUserPassword('developer@graines-digitales.online', 'super-admin-password');
        $this->assertUserPassword('johan.remy@graines-digitales.online', 'mlk-password');
        $this->assertUserPassword('buzac@mlk-my-little-kasbah.immo', 'mlk-password');
        $this->assertUserPassword('demo@graines-digitales.online', 'blue-bay-password');
        $this->assertUserPassword('manager@blue-bay-mogador.test', 'blue-bay-password');
    }

    public function testCommandRejectsUnknownPasswordProject(): void
    {
        $tester = $this->makeCommandTester();

        $tester->execute([
            '--password' => 'password123',
            '--password-project' => 'unknown-project',
        ], ['decorated' => false]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Unsupported password project', $tester->getDisplay());
    }

    private function makeCommandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:bootstrap:manual'));
    }

    private function assertUserPassword(string $email, string $password): void
    {
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        self::assertTrue($hasher->isPasswordValid($user, $password));
    }
}
