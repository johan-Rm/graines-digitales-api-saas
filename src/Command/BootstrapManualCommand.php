<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\ProjectRole;
use App\Security\PlatformSuperAdminResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:bootstrap:manual',
    description: 'Create or update the local backend bootstrap described in docs/10-operations/bootstrap-manuel.md.',
)]
final class BootstrapManualCommand extends Command
{
    private const SUPER_ADMIN_EMAIL = 'developer@graines-digitales.online';
    private const PROJECT_BLUE_BAY = 'blue-bay-mogador';
    private const PROJECT_MLK = 'mlk-modern-site-web';

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
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password applied to bootstrap users.')
            ->addOption(
                'password-project',
                null,
                InputOption::VALUE_REQUIRED,
                'Restrict password updates to users of a bootstrap project.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = (string) $input->getOption('password');
        $passwordProject = $this->resolvePasswordProject($input->getOption('password-project'));

        if ('' === trim($password)) {
            $io->error('Bootstrap password cannot be empty.');

            return Command::FAILURE;
        }

        if (false === $passwordProject) {
            $io->error(sprintf(
                'Unsupported password project. Expected one of: %s.',
                implode(', ', [self::PROJECT_BLUE_BAY, self::PROJECT_MLK]),
            ));

            return Command::FAILURE;
        }

        if (!$this->superAdminResolver->isSuperAdminEmail(self::SUPER_ADMIN_EMAIL)) {
            $io->warning(sprintf(
                'Email "%s" is not listed in PLATFORM_SUPER_ADMIN_EMAILS. The account is created, but runtime super admin access depends on that env var.',
                self::SUPER_ADMIN_EMAIL,
            ));
        }

        $this->entityManager->wrapInTransaction(function () use ($password, $passwordProject): void {
            $this->upsertUser(
                email: self::SUPER_ADMIN_EMAIL,
                password: $password,
                roles: ['ROLE_SUPER_ADMIN'],
                updatePassword: null === $passwordProject,
            );

            $mlkPasswordScoped = self::PROJECT_MLK === $passwordProject || null === $passwordProject;
            $mlkOwner = $this->upsertUser(
                'johan.remy@graines-digitales.online',
                $password,
                ['ROLE_ADMIN'],
                $mlkPasswordScoped,
            );
            $mlkAdmin = $this->upsertUser(
                'buzac@mlk-my-little-kasbah.immo',
                $password,
                ['ROLE_ADMIN'],
                $mlkPasswordScoped,
            );
            $mlkOrganization = $this->upsertOrganization(
                name: 'MLK - My Little Kasbah',
                acronym: 'MLK',
                fullName: 'MLK - My Little Kasbah',
                location: 'Essaouira',
                email: ['contact@mlk-my-little-kasbah.immo'],
                phoneNumbers: ['+33 (0)7 67 235 008', '+212 (0)7 26 403 203'],
                description: 'MLK – My Little Kasbah accompagne vos projets immobiliers à Essaouira : investissement, achat, vente et gestion locative avec une expertise locale et internationale.',
            );
            $this->upsertOrganizationMember($mlkOrganization, $mlkOwner, OrganizationRole::Owner);
            $this->upsertOrganizationMember($mlkOrganization, $mlkAdmin, OrganizationRole::Admin);
            $mlkProject = $this->upsertProject(
                organization: $mlkOrganization,
                name: 'MLK - Modern Site Web',
                sourceLocale: 'fr',
                enabledLocales: ['fr', 'en', 'es'],
                mediaPath: 'mlk-my-little-kasbah/public/images',
                mediaBaseUrl: '/images',
                watermarkPath: 'mlk-my-little-kasbah/app/assets/logo/watermark4.png',
            );
            $this->upsertProjectMember($mlkProject, $mlkOwner, ProjectRole::Owner);
            $this->upsertProjectMember($mlkProject, $mlkAdmin, ProjectRole::Admin);

            $blueBayPasswordScoped = self::PROJECT_BLUE_BAY === $passwordProject || null === $passwordProject;
            $blueBayOwner = $this->upsertUser(
                'demo@graines-digitales.online',
                $password,
                ['ROLE_ADMIN'],
                $blueBayPasswordScoped,
            );
            $blueBayAdmin = $this->upsertUser(
                'manager@blue-bay-mogador.test',
                $password,
                ['ROLE_ADMIN'],
                $blueBayPasswordScoped,
            );
            $blueBayOrganization = $this->upsertOrganization(
                name: 'Blue Bay Mogador',
                acronym: 'BBM',
                fullName: 'Blue Bay Mogador',
                location: 'Essaouira',
                email: ['contact@blue-bay-mogador.test'],
                phoneNumbers: ['+212 (0)6 12 345 678'],
                description: "Blue Bay Mogador est une organisation de demonstration dediee aux tests multi-projets et a la validation de l'isolation des donnees.",
            );
            $this->upsertOrganizationMember($blueBayOrganization, $blueBayOwner, OrganizationRole::Owner);
            $this->upsertOrganizationMember($blueBayOrganization, $blueBayAdmin, OrganizationRole::Admin);
            $blueBayProject = $this->upsertProject(
                organization: $blueBayOrganization,
                name: 'blue-bay-mogador',
                sourceLocale: 'fr',
                enabledLocales: ['fr', 'en'],
                mediaPath: 'blue-bay-mogador/public/images',
                mediaBaseUrl: '/images',
                watermarkPath: 'blue-bay-mogador/app/assets/logo/watermark4.png',
            );
            $this->upsertProjectMember($blueBayProject, $blueBayOwner, ProjectRole::Owner);
            $this->upsertProjectMember($blueBayProject, $blueBayAdmin, ProjectRole::Admin);
        });

        $mlkProject = $this->findProjectBySlug('mlk-modern-site-web');
        $blueBayProject = $this->findProjectBySlug('blue-bay-mogador');

        $io->success('Manual bootstrap data is ready.');
        $io->table(
            ['Project', 'Slug', 'UUID', 'Frontend env'],
            [
                [
                    'MLK - Modern Site Web',
                    'mlk-modern-site-web',
                    $mlkProject instanceof Project ? (string) $mlkProject->getId() : 'not found',
                    $mlkProject instanceof Project ? 'SYMFONY_PROJECT_ID='.(string) $mlkProject->getId() : '',
                ],
                [
                    'Blue Bay Mogador',
                    'blue-bay-mogador',
                    $blueBayProject instanceof Project ? (string) $blueBayProject->getId() : 'not found',
                    $blueBayProject instanceof Project ? 'SYMFONY_PROJECT_ID='.(string) $blueBayProject->getId() : '',
                ],
            ],
        );

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $roles
     */
    private function upsertUser(string $email, string $password, array $roles, bool $updatePassword): User
    {
        $email = strtolower(trim($email));
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $this->entityManager->persist($user);
            $updatePassword = true;
        }

        $user->setRoles($roles);

        if ($updatePassword) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        return $user;
    }

    /**
     * @return string|null|false
     */
    private function resolvePasswordProject(mixed $value): string|null|false
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $project = trim($value);

        if (in_array($project, [self::PROJECT_BLUE_BAY, self::PROJECT_MLK], true)) {
            return $project;
        }

        return false;
    }

    /**
     * @param list<string> $email
     * @param list<string> $phoneNumbers
     */
    private function upsertOrganization(
        string $name,
        string $acronym,
        string $fullName,
        string $location,
        array $email,
        array $phoneNumbers,
        string $description,
    ): Organization {
        $probe = new Organization();
        $probe->setName($name);
        $organization = $this->entityManager->getRepository(Organization::class)->findOneBy(['slug' => $probe->getSlug()]);

        if (!$organization instanceof Organization) {
            $organization = new Organization();
            $this->entityManager->persist($organization);
        }

        $organization
            ->setName($name)
            ->setAcronym($acronym)
            ->setFullName($fullName)
            ->setLocation($location)
            ->setEmail($email)
            ->setPhoneNumbers($phoneNumbers)
            ->setDescription($description);

        return $organization;
    }

    /**
     * @param list<string> $enabledLocales
     */
    private function upsertProject(
        Organization $organization,
        string $name,
        string $sourceLocale,
        array $enabledLocales,
        string $mediaPath,
        string $mediaBaseUrl,
        ?string $watermarkPath = null,
    ): Project {
        $probe = new Project();
        $probe->setName($name);
        $project = $this->findProjectBySlug($probe->getSlug());

        if (!$project instanceof Project) {
            $project = new Project();
            $this->entityManager->persist($project);
        }

        $project
            ->setOrganization($organization)
            ->setName($name)
            ->setSourceLocale($sourceLocale)
            ->setEnabledLocales($enabledLocales)
            ->setMediaPath($mediaPath)
            ->setMediaBaseUrl($mediaBaseUrl)
            ->setWatermarkPath($watermarkPath);

        return $project;
    }

    private function upsertOrganizationMember(Organization $organization, User $user, OrganizationRole $role): OrganizationMember
    {
        $member = $this->entityManager->getRepository(OrganizationMember::class)->findOneBy([
            'organization' => $organization,
            'user' => $user,
        ]);

        if (!$member instanceof OrganizationMember) {
            $member = new OrganizationMember();
            $member->setOrganization($organization);
            $member->setUser($user);
            $this->entityManager->persist($member);
        }

        $member->setRole($role);

        return $member;
    }

    private function upsertProjectMember(Project $project, User $user, ProjectRole $role): ProjectMember
    {
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);

        if (!$member instanceof ProjectMember) {
            $member = new ProjectMember();
            $member->setProject($project);
            $member->setUser($user);
            $this->entityManager->persist($member);
        }

        $member->setRole($role);

        return $member;
    }

    private function findProjectBySlug(string $slug): ?Project
    {
        $project = $this->entityManager->getRepository(Project::class)->findOneBy(['slug' => $slug]);

        return $project instanceof Project ? $project : null;
    }
}
