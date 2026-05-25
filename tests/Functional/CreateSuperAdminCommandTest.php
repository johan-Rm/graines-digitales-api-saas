<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateSuperAdminCommandTest extends FunctionalTestCase
{
    private function makeCommandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:user:create-super-admin'));
    }

    public function testCommandCreatesNewSuperAdminWithRoleAdmin(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'email' => 'newsuperadmin@example.test',
            '--password' => 'securepassword123',
        ], ['decorated' => false]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('created', $tester->getDisplay());

        $user = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'newsuperadmin@example.test',
        ]);

        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testCommandIsIdempotentForExistingUser(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'email' => 'admin@grainesdigitales.local',
            '--password' => 'newpassword123',
        ], ['decorated' => false]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('updated', $tester->getDisplay());

        $user = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);

        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testCommandWarnsWhenEmailNotInSuperAdminList(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'email' => 'not.in.list@example.test',
            '--password' => 'securepassword123',
        ], ['decorated' => false]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('PLATFORM_SUPER_ADMIN_EMAILS', $tester->getDisplay());
    }

    public function testCommandDoesNotWarnForEmailInSuperAdminList(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'email' => 'admin@grainesdigitales.local',
            '--password' => 'securepassword123',
        ], ['decorated' => false]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('PLATFORM_SUPER_ADMIN_EMAILS', $tester->getDisplay());
    }

    public function testCommandFailsWhenCreatingUserWithoutPassword(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'email' => 'nopwd@example.test',
        ], ['interactive' => false, 'decorated' => false]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('password is required', $tester->getDisplay());
    }

    public function testCommandRejectsInvalidEmail(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'email' => 'not-an-email',
            '--password' => 'securepassword123',
        ], ['decorated' => false]);

        self::assertSame(1, $tester->getStatusCode());
    }

    public function testCommandUpdatesExistingUserWithoutPasswordChange(): void
    {
        $userBefore = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);
        self::assertInstanceOf(User::class, $userBefore);
        $hashBefore = $userBefore->getPassword();

        $tester = $this->makeCommandTester();
        $tester->execute([
            'email' => 'admin@grainesdigitales.local',
        ], ['interactive' => false, 'decorated' => false]);

        self::assertSame(0, $tester->getStatusCode());

        $this->entityManager()->clear();
        $userAfter = $this->entityManager()->getRepository(User::class)->findOneBy([
            'email' => 'admin@grainesdigitales.local',
        ]);
        self::assertInstanceOf(User::class, $userAfter);
        self::assertSame($hashBefore, $userAfter->getPassword());
    }
}
