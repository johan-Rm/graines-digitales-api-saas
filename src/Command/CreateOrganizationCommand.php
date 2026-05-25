<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:organization:create',
    description: 'Create a new organization, optionally with an owner.'
)]
final class CreateOrganizationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The organization name.')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Override the auto-generated slug.')
            ->addOption('owner-email', null, InputOption::VALUE_REQUIRED, 'Email of an existing user to set as owner.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = trim((string) $input->getArgument('name'));
        if ('' === $name) {
            $io->error('Organization name cannot be empty.');

            return Command::FAILURE;
        }

        $organization = new Organization();
        $organization->setName($name);

        $slugOption = $input->getOption('slug');
        if (is_string($slugOption) && '' !== trim($slugOption)) {
            $organization->setSlug(trim($slugOption));
        }

        $slug = $organization->getSlug();
        $existing = $this->entityManager->getRepository(Organization::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Organization) {
            $io->error(sprintf('An organization with slug "%s" already exists.', $slug));

            return Command::FAILURE;
        }

        $ownerEmail = $input->getOption('owner-email');
        $owner = null;

        if (is_string($ownerEmail) && '' !== trim($ownerEmail)) {
            $ownerEmail = strtolower(trim($ownerEmail));
            $owner = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $ownerEmail]);

            if (!$owner instanceof User) {
                $io->error(sprintf('No user found with email "%s".', $ownerEmail));

                return Command::FAILURE;
            }
        }

        $this->entityManager->persist($organization);

        if ($owner instanceof User) {
            $member = new OrganizationMember();
            $member->setOrganization($organization);
            $member->setUser($owner);
            $member->setRole(OrganizationRole::Owner);
            $this->entityManager->persist($member);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Organization created: %s (slug: %s)', $organization->getName(), $organization->getSlug()));

        if ($owner instanceof User) {
            $io->note(sprintf('Owner assigned: %s', $owner->getEmail()));
        }

        return Command::SUCCESS;
    }
}
