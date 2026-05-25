<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Security\PlatformSuperAdminResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-super-admin',
    description: 'Create or update a super admin user (ROLE_ADMIN in DB, super admin via PLATFORM_SUPER_ADMIN_EMAILS).'
)]
final class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlatformSuperAdminResolver $superAdminResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The super admin email address.')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'The password (omit to be prompted interactively).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));

        if ('' === $email) {
            $io->error('Email cannot be empty.');

            return Command::FAILURE;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('"%s" is not a valid email address.', $email));

            return Command::FAILURE;
        }

        if (!$this->superAdminResolver->isSuperAdminEmail($email)) {
            $io->warning(sprintf(
                'Email "%s" is not listed in PLATFORM_SUPER_ADMIN_EMAILS. '
                .'The user will have ROLE_ADMIN in DB but will NOT be recognized as super admin at runtime.',
                $email
            ));
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $isNew = !$user instanceof User;

        $password = $this->resolvePassword($input, $io, $isNew);

        if ($isNew && '' === $password) {
            $io->error('A password is required to create a new super admin user.');

            return Command::FAILURE;
        }

        if ($isNew) {
            $user = new User();
            $user->setEmail($email);
            $this->entityManager->persist($user);
        }

        $user->setRoles(['ROLE_ADMIN']);

        if ('' !== $password) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        $this->entityManager->flush();

        $action = $isNew ? 'created' : 'updated';
        $io->success(sprintf('Super admin user %s: %s', $action, $email));

        if ('' === $password && !$isNew) {
            $io->note('Password was not changed (no password provided).');
        }

        return Command::SUCCESS;
    }

    private function resolvePassword(InputInterface $input, SymfonyStyle $io, bool $isNew): string
    {
        $option = $input->getOption('password');

        if (is_string($option) && '' !== $option) {
            return $option;
        }

        if (!$input->isInteractive()) {
            return '';
        }

        $prompt = $isNew
            ? 'Password'
            : 'New password (leave empty to keep current password)';

        return (string) $io->askHidden($prompt) ?? '';
    }
}
