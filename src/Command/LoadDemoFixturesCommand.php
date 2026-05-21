<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\Accommodation;
use App\Entity\AccommodationMedia;
use App\Entity\CategoryCode;
use App\Entity\MediaObject;
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
    description: 'Load stable demo data for local development and functional tests.'
)]
final class LoadDemoFixturesCommand extends Command
{
    private const DEMO_PASSWORD = 'password123';

    /**
     * @var array<string, CategoryCode>
     */
    private array $categoryCodeCache = [];

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
        $this->upsertPropertyListings($riadProject, $villaProject, $coastalProject, $admin);

        $this->entityManager->flush();

        $io->success([
            'Demo fixtures loaded.',
            'Users password: '.self::DEMO_PASSWORD,
            'Organizations: 2',
            'Projects: 3',
            'Real estate listings: 5',
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
        $this->upsertCategoryCode($project, 'content-category', $categoryCode, $headline, $actor);
        $this->upsertWebPage($project, 'accueil', $headline, $description, $actor);
        $this->upsertArticle($project, 'note-marche-essaouira', $headline, $description, $actor);
        $this->upsertMenuItem($project, 'Accueil', '/', 1, $actor);
        $this->upsertMenuItem($project, 'Contact', '/contact', 2, $actor);
    }

    private function upsertPropertyListings(Project $riadProject, Project $villaProject, Project $coastalProject, User $actor): void
    {
        $listings = [
            [
                'project' => $riadProject,
                'identifier' => 'BAVR001',
                'slug' => 'bavr001-riad-hotel-central-en-medina-medina',
                'name' => 'Riad hotel central en medina',
                'highlight' => 'Un riad hotel exploitable immediatement au coeur de la medina',
                'body' => 'Riad hotel de 20 chambres organise autour d un patio central lumineux, avec terrasse et potentiel de repositionnement haut de gamme.',
                'category' => 'riad',
                'place' => 'medina',
                'floorSize' => '238',
                'areaSize' => 238,
                'numberOfRooms' => 20,
                'numberOfBedrooms' => 20,
                'numberOfBathroomsTotal' => 20,
                'occupancy' => 40,
                'amenityFeature' => ['patio', 'terrasse', 'exploitation-commerciale'],
                'offer' => [
                    'price' => '8400000',
                    'priceCurrency' => 'MAD',
                    'priceSpecification' => 'A la vente',
                ],
                'realEstateListing' => [
                    'slug' => 'bien-a-vendre',
                    'name' => 'Bien a vendre',
                ],
                'associatedMedia' => [
                    [
                        'image' => 'bavr001-riad-hotel-central-patio-arcades-pierre-zellige-01',
                        'caption' => 'Patio central avec arcades en pierre',
                        'keywords' => ['gallery'],
                    ],
                ],
                'tags' => ['medina', 'hotel', 'investissement'],
                'metaTitle' => 'Riad hotel a vendre medina Essaouira',
                'metaDescription' => 'Riad hotel de 20 chambres avec patio et terrasse au coeur de la medina d Essaouira.',
                'isActive' => true,
            ],
            [
                'project' => $coastalProject,
                'identifier' => 'FBAVVG001',
                'name' => 'Villa golf avec jardin a Mogador',
                'slug' => 'villa-golf-avec-jardin-a-mogador-golf-mogador-bavvg001',
                'body' => 'Villa de standing avec jardin et piscine dans le secteur golf, adaptee a une clientele residentielle recherchant calme et espace.',
                'category' => 'villa-golf',
                'place' => 'golf-mogador',
                'yearBuilt' => 2019,
                'floorSize' => '320',
                'areaSize' => 320,
                'landArea' => '980',
                'numberOfRooms' => 7,
                'numberOfBedrooms' => 4,
                'numberOfBathroomsTotal' => 4,
                'numberOfGarages' => 2,
                'occupancy' => 8,
                'amenityFeature' => ['piscine', 'jardin', 'garage'],
                'offer' => [
                    'price' => '1950000',
                    'priceCurrency' => 'EUR',
                    'priceSpecification' => 'A la vente',
                ],
                'realEstateListing' => [
                    'slug' => 'bien-a-vendre',
                    'name' => 'Bien a vendre',
                ],
                'associatedMedia' => [
                    [
                        'image' => 'media-villa-golf-jardin-mogador-01',
                        'caption' => 'Villa golf Mogador - jardin',
                        'keywords' => ['gallery'],
                    ],
                ],
                'tags' => ['golf', 'jardin', 'piscine'],
                'metaTitle' => 'Villa golf 320 m2 avec jardin a Mogador',
                'metaDescription' => 'Villa a vendre avec 4 chambres, jardin et piscine dans le secteur golf.',
                'isActive' => true,
            ],
            [
                'project' => $coastalProject,
                'identifier' => 'FBAVA004',
                'name' => 'Appartement marina avec terrasse',
                'slug' => 'appartement-marina-avec-terrasse-marina-bava004',
                'highlight' => 'Un pied-a-terre lumineux en marina',
                'body' => 'Appartement lumineux a la marina, avec terrasse, parking et plan compact pour residence secondaire ou location saisonniere.',
                'category' => 'appartement',
                'place' => 'marina',
                'yearBuilt' => 2016,
                'floorSize' => '78',
                'areaSize' => 78,
                'areaTerrace' => 12,
                'numberOfRooms' => 2,
                'numberOfBedrooms' => 1,
                'numberOfBathroomsTotal' => 1,
                'numberOfGarages' => 0,
                'occupancy' => 2,
                'amenityFeature' => ['terrasse', 'ascenseur', 'parking'],
                'offer' => [
                    'price' => '145000',
                    'priceCurrency' => 'EUR',
                    'priceSpecification' => 'A la vente',
                ],
                'realEstateListing' => [
                    'slug' => 'bien-a-vendre',
                    'name' => 'Bien a vendre',
                ],
                'associatedMedia' => [
                    [
                        'image' => 'media-appartement-marina-essaouira-02',
                        'caption' => 'Appartement marina Essaouira - interieur clair',
                        'keywords' => ['gallery'],
                    ],
                ],
                'tags' => ['marina', 'terrasse', 'parking'],
                'metaTitle' => 'Appartement marina 78 m2 avec terrasse',
                'metaDescription' => 'Appartement de 78 m2 a la marina, 1 chambre, terrasse et parking.',
                'isActive' => true,
            ],
            [
                'project' => $villaProject,
                'identifier' => 'BAVT001',
                'name' => 'Terrain constructible vue mer',
                'slug' => 'bavt001-terrain-constructible-vue-mer-moulay-bouzerktoun',
                'body' => 'Terrain constructible a Moulay Bouzerktoun avec vue degagee sur l ocean, dans un environnement calme proche d Essaouira.',
                'category' => 'terrain',
                'place' => 'moulay-bouzerktoun',
                'landArea' => '550/580',
                'floorSize' => '550/580',
                'numberOfBedrooms' => 0,
                'offer' => [
                    'price' => '409500',
                    'priceCurrency' => 'MAD',
                    'priceSpecification' => 'A la vente',
                ],
                'realEstateListing' => [
                    'slug' => 'bien-a-vendre',
                    'name' => 'Bien a vendre',
                ],
                'associatedMedia' => [
                    [
                        'image' => 'bavt001-terrain-moulay-bouzerktoun-vue-mer-01',
                        'caption' => 'Terrain constructible avec vue degagee sur l ocean a Moulay Bouzerktoun',
                        'keywords' => ['terrain', 'vue mer'],
                    ],
                ],
                'tags' => ['terrain', 'vue-mer'],
                'metaTitle' => 'Terrain constructible vue mer Moulay Bouzerktoun',
                'metaDescription' => 'Terrain constructible avec vue degagee sur l ocean pres d Essaouira.',
                'isActive' => true,
            ],
            [
                'project' => $villaProject,
                'identifier' => 'BAVLC001',
                'name' => 'Superbe local commercial a Erraounak',
                'slug' => 'bavlc001-local-commercial-27m2-erraounak-essaouira',
                'body' => 'Local commercial renove de 27 m2 avec vitrine sur rue passante, situe dans un secteur dynamique d Essaouira.',
                'category' => 'local-commercial',
                'place' => 'nouvelle-ville',
                'floorSize' => '27',
                'areaSize' => 27,
                'numberOfBedrooms' => 0,
                'offer' => [
                    'price' => '693000',
                    'priceCurrency' => 'MAD',
                    'priceSpecification' => 'A la vente',
                ],
                'realEstateListing' => [
                    'slug' => 'bien-a-vendre',
                    'name' => 'Bien a vendre',
                ],
                'associatedMedia' => [
                    [
                        'image' => 'bavlc001-local-commercial-erraounak-interieur-espace-01',
                        'caption' => 'Espace interieur renove avec finitions modernes',
                        'keywords' => ['gallery'],
                    ],
                ],
                'tags' => ['commerce', 'erraounak', 'investissement'],
                'metaTitle' => 'Local commercial a vendre Essaouira Erraounak 27 m2',
                'metaDescription' => 'Local commercial renove de 27 m2 a vendre a Erraounak Essaouira avec vitrine.',
                'isActive' => true,
            ],
        ];

        foreach ($listings as $listing) {
            $this->upsertAccommodation($listing, $actor);
        }
    }

    /**
     * @param array<string, mixed> $listing
     */
    private function upsertAccommodation(array $listing, User $actor): Accommodation
    {
        $project = $listing['project'];
        \assert($project instanceof Project);

        $accommodation = $this->entityManager->getRepository(Accommodation::class)->findOneBy([
            'project' => $project,
            'identifier' => $listing['identifier'],
        ]);

        if (!$accommodation instanceof Accommodation) {
            $accommodation = new Accommodation();
            $accommodation->setProject($project);
            $accommodation->setIdentifier($listing['identifier']);
            $accommodation->markCreatedBy($actor);
            $this->entityManager->persist($accommodation);
        }

        $offer = $listing['offer'];
        $realEstateListing = $listing['realEstateListing'];
        \assert(\is_array($offer));
        \assert(\is_array($realEstateListing));

        $accommodation->setCurrentLocale('fr');
        $accommodation
            ->setSlug($listing['slug'])
            ->setName($listing['name'])
            ->setLabel($listing['label'] ?? null)
            ->setHighlight($listing['highlight'] ?? null)
            ->setBody($listing['body'] ?? null)
            ->setReview($listing['review'] ?? null)
            ->setMetaTitle($listing['metaTitle'] ?? null)
            ->setMetaDescription($listing['metaDescription'] ?? null)
            ->setCategory($this->upsertCategoryCode($project, 'accommodation-type', $listing['category'], $this->labelFromCode($listing['category']), $actor))
            ->setRealEstateListing($this->upsertCategoryCode($project, 'real-estate-listing', $realEstateListing['slug'], $realEstateListing['name'], $actor))
            ->setPlace($this->upsertCategoryCode($project, 'accommodation-place', $listing['place'], $this->labelFromCode($listing['place']), $actor))
            ->setStatus(ContentStatus::Published)
            ->setYearBuilt($listing['yearBuilt'] ?? null)
            ->setAreaSize($listing['areaSize'] ?? null)
            ->setAreaTerrace($listing['areaTerrace'] ?? null)
            ->setFloorSize($listing['floorSize'] ?? null)
            ->setLandArea($listing['landArea'] ?? null)
            ->setLevel($listing['level'] ?? null)
            ->setNumberOfRooms($listing['numberOfRooms'] ?? null)
            ->setNumberOfBedrooms($listing['numberOfBedrooms'] ?? null)
            ->setNumberOfBathroomsTotal($listing['numberOfBathroomsTotal'] ?? null)
            ->setNumberOfGarages($listing['numberOfGarages'] ?? null)
            ->setOccupancy($listing['occupancy'] ?? null)
            ->setIsActive($listing['isActive'] ?? true)
            ->setOfferPrice($offer['price'] ?? null)
            ->setOfferPriceCurrency($offer['priceCurrency'] ?? null)
            ->setOfferPriceSpecification($offer['priceSpecification'] ?? null)
            ->setOfferAvailability($offer['availability'] ?? null)
            ->setRealEstateAgentIdentifier('gd-demo-agent')
            ->setRealEstateAgentName('Graines Digitales Immobilier')
            ->setRealEstateAgentPhone('+212 000 000 000')
            ->setRealEstateAgentEmail('contact@grainesdigitales.local')
            ->touch($actor);

        $accommodation->clearAmenityFeature();
        foreach ($listing['amenityFeature'] ?? [] as $amenityCode) {
            $accommodation->addAmenityFeature($this->upsertCategoryCode($project, 'amenity-feature', $amenityCode, $this->labelFromCode($amenityCode), $actor));
        }

        $accommodation->clearTags();
        foreach ($listing['tags'] ?? [] as $tagCode) {
            $accommodation->addTag($this->upsertCategoryCode($project, 'tag', $tagCode, $this->labelFromCode($tagCode), $actor));
        }

        $accommodation->clearAssociatedMedia();
        foreach ($listing['associatedMedia'] ?? [] as $position => $mediaData) {
            \assert(\is_array($mediaData));
            $media = new AccommodationMedia();
            $media
                ->setMediaObject($this->upsertMediaObject($project, $mediaData['image'], $mediaData['caption'], $actor))
                ->setPosition($position + 1)
                ->setCaption($mediaData['caption'])
                ->setKeywords($mediaData['keywords'] ?? []);

            $accommodation->addAssociatedMedia($media);
        }

        return $accommodation;
    }

    private function upsertCategoryCode(Project $project, string $inCodeSet, string $code, string $label, User $actor): CategoryCode
    {
        $cacheKey = (string) $project->getId().':'.$inCodeSet.':'.$code;
        if (isset($this->categoryCodeCache[$cacheKey])) {
            $category = $this->categoryCodeCache[$cacheKey];
            $category->setCurrentLocale('fr');
            $category
                ->setLabel($label)
                ->setMetadata(['fixture' => 'demo'])
                ->touch($actor);

            return $category;
        }

        $category = $this->entityManager->getRepository(CategoryCode::class)->findOneBy([
            'project' => $project,
            'inCodeSet' => $inCodeSet,
            'code' => $code,
        ]);

        if (!$category instanceof CategoryCode) {
            $category = new CategoryCode();
            $category->setProject($project);
            $category->setInCodeSet($inCodeSet);
            $category->setCode($code);
            $category->markCreatedBy($actor);
            $this->entityManager->persist($category);
        }

        $category->setCurrentLocale('fr');
        $category
            ->setInCodeSet($inCodeSet)
            ->setLabel($label)
            ->setMetadata(['fixture' => 'demo'])
            ->touch($actor);

        $this->categoryCodeCache[$cacheKey] = $category;

        return $category;
    }

    private function upsertMediaObject(Project $project, string $filePath, string $caption, User $actor): MediaObject
    {
        $mediaObject = $this->entityManager->getRepository(MediaObject::class)->findOneBy([
            'project' => $project,
            'filePath' => $filePath.'.jpg',
        ]);

        if (!$mediaObject instanceof MediaObject) {
            $mediaObject = new MediaObject();
            $mediaObject->setProject($project);
            $mediaObject->markCreatedBy($actor);
            $this->entityManager->persist($mediaObject);
        }

        $mediaObject
            ->setFilePath($filePath.'.jpg')
            ->setMimeType('image/jpeg')
            ->setSize(0)
            ->setOriginalFilename($filePath.'.jpg')
            ->setChecksumSha256(hash('sha256', $project->getSlug().':'.$filePath))
            ->setFinalAlt($caption)
            ->setFinalDescription($caption)
            ->touch($actor);

        return $mediaObject;
    }

    private function labelFromCode(string $code): string
    {
        return ucwords(str_replace('-', ' ', $code));
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
