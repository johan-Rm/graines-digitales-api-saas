<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\CategoryCode;
use App\Entity\MenuItem;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Entity\WebPage;
use App\Entity\WebPageTranslation;
use App\Enum\ContentStatus;
use App\Enum\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:fixtures:load-demo',
    description: 'Load stable demo data for local development and functional tests.',
)]
final class LoadDemoFixturesCommand extends Command
{
    private const DEMO_PASSWORD = 'password123';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $admin = $this->upsertUser('admin@grainesdigitales.local', ['ROLE_ADMIN']);
        $editor = $this->upsertUser('editor.essaouira@grainesdigitales.local', ['ROLE_USER']);
        $viewer = $this->upsertUser('viewer.mogador@grainesdigitales.local', ['ROLE_USER']);

        $heritage = $this->upsertOrganization('Essaouira Patrimoine Immobilier', 'essaouira-patrimoine-immobilier', $admin);
        $coastal = $this->upsertOrganization('Atlas Coastal Living', 'atlas-coastal-living', $admin);

        $riadProject = $this->upsertProject(
            $heritage,
            'Riads Medina Essaouira',
            'riads-medina-essaouira',
            ['fr', 'en'],
            $admin,
        );
        $villaProject = $this->upsertProject(
            $heritage,
            'Villas Arganier Ghazoua',
            'villas-arganier-ghazoua',
            ['fr', 'en'],
            $admin,
        );
        $coastalProject = $this->upsertProject(
            $coastal,
            'Blue Bay Mogador',
            'blue-bay-mogador',
            ['fr', 'en', 'es'],
            $admin,
        );

        foreach ([$riadProject, $villaProject, $coastalProject] as $project) {
            $this->upsertProjectMember($project, $admin, ProjectRole::Owner, $admin);
        }

        $this->upsertProjectMember($riadProject, $editor, ProjectRole::Editor, $admin);
        $this->upsertProjectMember($villaProject, $editor, ProjectRole::Editor, $admin);
        $this->upsertProjectMember($coastalProject, $viewer, ProjectRole::Viewer, $admin);

        $this->upsertProjectContent(
            $riadProject,
            'riad',
            'Riad authentique dans la medina',
            'Une selection de riads calmes pour explorer le marche immobilier de la medina d Essaouira.',
            $admin,
        );
        $this->upsertProjectContent(
            $villaProject,
            'villa',
            'Villas familiales a Ghazoua',
            'Des villas sobres avec jardin, pensees pour les familles et les sejours longue duree.',
            $admin,
        );
        $this->upsertProjectContent(
            $coastalProject,
            'coastal-villa',
            'Maisons avec vue ocean a Mogador',
            'Un portefeuille cotier pour tester les contenus multilingues et les parcours premium.',
            $admin,
        );

        $this->entityManager->flush();

        $io->success([
            'Demo fixtures loaded.',
            'Users password: '.self::DEMO_PASSWORD,
            'Organizations: 2',
            'Projects: 3',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $roles
     */
    private function upsertUser(string $email, array $roles): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $this->entityManager->persist($user);
        }

        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::DEMO_PASSWORD));

        return $user;
    }

    private function upsertOrganization(string $name, string $slug, User $actor): Organization
    {
        $organization = $this->entityManager->getRepository(Organization::class)->findOneBy(['slug' => $slug]);
        if (!$organization instanceof Organization) {
            $organization = new Organization();
            $organization->setSlug($slug);
            $organization->markCreatedBy($actor);
            $this->entityManager->persist($organization);
        }

        $organization->setName($name);
        $organization->touch($actor);

        return $organization;
    }

    /**
     * @param list<string> $enabledLocales
     */
    private function upsertProject(
        Organization $organization,
        string $name,
        string $slug,
        array $enabledLocales,
        User $actor,
    ): Project {
        $project = $this->entityManager->getRepository(Project::class)->findOneBy(['slug' => $slug]);
        if (!$project instanceof Project) {
            $project = new Project();
            $project->setSlug($slug);
            $project->markCreatedBy($actor);
            $this->entityManager->persist($project);
        }

        $project
            ->setOrganization($organization)
            ->setName($name)
            ->setSourceLocale('fr')
            ->setEnabledLocales($enabledLocales)
            ->touch($actor);

        return $project;
    }

    private function upsertProjectMember(Project $project, User $user, ProjectRole $role, User $actor): ProjectMember
    {
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);

        if (!$member instanceof ProjectMember) {
            $member = new ProjectMember();
            $member->setProject($project);
            $member->setUser($user);
            $member->markCreatedBy($actor);
            $this->entityManager->persist($member);
        }

        $member->setRole($role);
        $member->touch($actor);

        return $member;
    }

    private function upsertProjectContent(
        Project $project,
        string $categoryCode,
        string $headline,
        string $description,
        User $actor,
    ): void {
        $this->upsertCategoryCode($project, $categoryCode, $headline, $actor);
        $this->upsertWebPage($project, 'accueil', $headline, $description, $actor);
        $this->upsertArticle($project, 'note-marche-essaouira', $headline, $description, $actor);
        $this->upsertMenuItem($project, 'Accueil', '/', 1, $actor);
        $this->upsertMenuItem($project, 'Contact', '/contact', 2, $actor);
    }

    private function upsertCategoryCode(Project $project, string $code, string $label, User $actor): CategoryCode
    {
        $category = $this->entityManager->getRepository(CategoryCode::class)->findOneBy([
            'project' => $project,
            'code' => $code,
        ]);

        if (!$category instanceof CategoryCode) {
            $category = new CategoryCode();
            $category->setProject($project);
            $category->setCode($code);
            $category->markCreatedBy($actor);
            $this->entityManager->persist($category);
        }

        $category->setCurrentLocale('fr');
        $category
            ->setLabel($label)
            ->setMetadata(['fixture' => 'demo'])
            ->touch($actor);

        return $category;
    }

    private function upsertWebPage(Project $project, string $slug, string $headline, string $description, User $actor): WebPage
    {
        $page = $this->findTranslatableBySlug(WebPage::class, WebPageTranslation::class, $project, $slug);
        if (!$page instanceof WebPage) {
            $page = new WebPage();
            $page->setProject($project);
            $page->markCreatedBy($actor);
            $this->entityManager->persist($page);
        }

        $page->setCurrentLocale('fr');
        $page
            ->setSlug($slug)
            ->setMetaTitle($headline)
            ->setMetaDescription($description)
            ->setHeadline($headline)
            ->setBody($description)
            ->setStatus(ContentStatus::Published)
            ->setSchemaData(['@type' => 'WebPage'])
            ->touch($actor);

        return $page;
    }

    private function upsertArticle(Project $project, string $slug, string $headline, string $body, User $actor): Article
    {
        $article = $this->findTranslatableBySlug(Article::class, ArticleTranslation::class, $project, $slug);
        if (!$article instanceof Article) {
            $article = new Article();
            $article->setProject($project);
            $article->markCreatedBy($actor);
            $this->entityManager->persist($article);
        }

        $article->setCurrentLocale('fr');
        $article
            ->setSlug($slug)
            ->setHeadline($headline)
            ->setBody($body)
            ->setStatus(ContentStatus::Published)
            ->setSchemaData(['@type' => 'Article'])
            ->touch($actor);

        return $article;
    }

    private function upsertMenuItem(Project $project, string $label, string $url, int $position, User $actor): MenuItem
    {
        $menuItem = $this->entityManager->getRepository(MenuItem::class)->findOneBy([
            'project' => $project,
            'url' => $url,
        ]);

        if (!$menuItem instanceof MenuItem) {
            $menuItem = new MenuItem();
            $menuItem->setProject($project);
            $menuItem->markCreatedBy($actor);
            $this->entityManager->persist($menuItem);
        }

        $menuItem->setCurrentLocale('fr');
        $menuItem
            ->setLabel($label)
            ->setUrl($url)
            ->setPosition($position)
            ->setMetadata(['fixture' => 'demo'])
            ->touch($actor);

        return $menuItem;
    }

    /**
     * @param class-string $resourceClass
     * @param class-string $translationClass
     */
    private function findTranslatableBySlug(string $resourceClass, string $translationClass, Project $project, string $slug): ?object
    {
        return $this->entityManager->createQueryBuilder()
            ->select('resource')
            ->from($resourceClass, 'resource')
            ->innerJoin($translationClass, 'translation', 'WITH', 'translation.translatable = resource')
            ->andWhere('resource.project = :project')
            ->andWhere('translation.locale = :locale')
            ->andWhere('translation.slug = :slug')
            ->setParameter('project', $project)
            ->setParameter('locale', 'fr')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
