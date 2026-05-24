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
use Symfony\Component\Console\Input\InputOption;
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
    private const JOHAN_PASSWORD = 'password1234';

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

    protected function configure(): void
    {
        $this->addOption(
            'with-test-tenants',
            null,
            InputOption::VALUE_NONE,
            'Load additional legacy tenants used by the functional isolation tests.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $johan = $this->upsertUser('johan.remy@graines-digitales.online', ['ROLE_ADMIN'], self::JOHAN_PASSWORD);
        $caroline = $this->upsertUser('buzac@mlk-my-little-kasbah.immo', ['ROLE_USER'], self::DEMO_PASSWORD);

        $organization = $this->upsertOrganization('MLK - My Little Kasbah', 'mlk-my-little-kasbah', $johan);
        $project = $this->upsertProject(
            $organization,
            'MLK - Modern Site Web',
            'mlk-modern-site-web',
            ['fr', 'en', 'es'],
            $johan,
        );

        $this->upsertProjectMember($project, $johan, ProjectRole::Owner, $johan);
        $this->upsertProjectMember($project, $caroline, ProjectRole::Admin, $johan);

        $this->upsertProjectContent(
            $project,
            'immobilier-essaouira',
            'MLK - My Little Kasbah',
            'Une selection de biens immobiliers a Essaouira et sa region.',
            $johan,
        );
        $this->upsertDemoCategoryCodes($project, $johan);
        $this->upsertPropertyListings($johan, static fn (string $identifier): Project => $project);

        if (true === $input->getOption('with-test-tenants')) {
            $this->upsertLegacyFunctionalTestTenants($johan);
        }

        $this->entityManager->flush();

        $io->success([
            'Demo fixtures loaded.',
            'Johan password: '.self::JOHAN_PASSWORD,
            'Caroline password: '.self::DEMO_PASSWORD,
            'Organizations: 1',
            'Projects: 1',
            'Real estate listings: 5',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $roles
     */
    private function upsertUser(string $email, array $roles, string $password = self::DEMO_PASSWORD): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $this->entityManager->persist($user);
        }

        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

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

    private function upsertLegacyFunctionalTestTenants(User $actor): void
    {
        $admin = $this->upsertUser('admin@grainesdigitales.local', ['ROLE_ADMIN']);
        $editor = $this->upsertUser('editor.essaouira@grainesdigitales.local', ['ROLE_USER']);
        $viewer = $this->upsertUser('viewer.mogador@grainesdigitales.local', ['ROLE_USER']);

        $heritage = $this->upsertOrganization('Essaouira Patrimoine Immobilier', 'essaouira-patrimoine-immobilier', $actor);
        $coastal = $this->upsertOrganization('Atlas Coastal Living', 'atlas-coastal-living', $actor);

        $riadProject = $this->upsertProject($heritage, 'Riads Medina Essaouira', 'riads-medina-essaouira', ['fr', 'en'], $actor);
        $villaProject = $this->upsertProject($heritage, 'Villas Arganier Ghazoua', 'villas-arganier-ghazoua', ['fr', 'en', 'es'], $actor);
        $coastalProject = $this->upsertProject($coastal, 'Blue Bay Mogador', 'blue-bay-mogador', ['fr', 'en', 'es'], $actor);

        foreach ([$riadProject, $villaProject, $coastalProject] as $project) {
            $this->upsertProjectMember($project, $admin, ProjectRole::Owner, $actor);
            $this->upsertDemoCategoryCodes($project, $actor);
        }

        $this->upsertProjectMember($riadProject, $editor, ProjectRole::Editor, $actor);
        $this->upsertProjectMember($villaProject, $editor, ProjectRole::Editor, $actor);
        $this->upsertProjectMember($coastalProject, $viewer, ProjectRole::Viewer, $actor);

        $this->upsertProjectContent(
            $riadProject,
            'riad',
            'Riad authentique dans la medina',
            'Une selection de riads calmes pour explorer le marche immobilier de la medina d Essaouira.',
            $actor,
        );
        $this->upsertProjectContent(
            $villaProject,
            'villa',
            'Villas familiales a Ghazoua',
            'Des villas sobres avec jardin, pensees pour les familles et les sejours longue duree.',
            $actor,
        );
        $this->upsertProjectContent(
            $coastalProject,
            'coastal-villa',
            'Maisons avec vue ocean a Mogador',
            'Un portefeuille cotier pour tester les contenus multilingues et les parcours premium.',
            $actor,
        );

        $this->upsertPropertyListings($actor, static function (string $identifier) use ($riadProject, $villaProject, $coastalProject): Project {
            return match ($identifier) {
                'BAVR001' => $riadProject,
                'BAVT001', 'BAVLC001' => $villaProject,
                default => $coastalProject,
            };
        });
    }

    /**
     * @param callable(string): Project $resolveProject
     */
    private function upsertPropertyListings(User $actor, callable $resolveProject): void
    {
        $listings = [
            [
                'project' => $resolveProject('BAVR001'),
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
                'project' => $resolveProject('FBAVVG001'),
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
                'project' => $resolveProject('FBAVA004'),
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
                'project' => $resolveProject('BAVT001'),
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
                'project' => $resolveProject('BAVLC001'),
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

    private function upsertDemoCategoryCodes(Project $project, User $actor): void
    {
        foreach ($this->demoCategoryCodeFixtures() as $fixture) {
            $this->upsertCategoryCode(
                $project,
                $fixture['inCodeSet'],
                $fixture['code'],
                $fixture['label'],
                $actor,
                $fixture['metadata'] ?? [],
            );
        }
    }

    /**
     * @return list<array{inCodeSet: string, code: string, label: string, metadata?: array<string, mixed>}>
     */
    private function demoCategoryCodeFixtures(): array
    {
        $fixtures = [];

        foreach ($this->accommodationTypeFixtures() as $item) {
            $fixtures[] = $this->categoryCodeFixture('accommodation-type', $item['slug'], $item['name'], [
                'text' => $item['text'] ?? '',
            ]);
        }

        foreach ($this->accommodationPlaceFixtures() as $item) {
            $fixtures[] = $this->categoryCodeFixture('accommodation-place', $item['slug'], $item['name'], [
                'description' => $item['description'] ?? '',
            ]);
        }

        foreach ($this->realEstateListingFixtures() as $item) {
            $fixtures[] = $this->categoryCodeFixture('real-estate-listing', $item['slug'], $item['name'], [
                'isActive' => $item['isActive'] ?? true,
                'text' => $item['text'] ?? '',
            ]);
        }

        foreach ($this->genericCategoryCodeFixtures() as $item) {
            $fixtures[] = $this->categoryCodeFixture($item['inCodeSet'], $item['codeValue'], $item['name']);
        }

        return $fixtures;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{inCodeSet: string, code: string, label: string, metadata?: array<string, mixed>}
     */
    private function categoryCodeFixture(string $inCodeSet, string $code, string $label, array $metadata = []): array
    {
        $metadata = array_filter($metadata, static fn (mixed $value): bool => null !== $value && '' !== $value);

        return [
            'inCodeSet' => $inCodeSet,
            'code' => $code,
            'label' => $label,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return list<array{slug: string, name: string, text?: string}>
     */
    private function accommodationTypeFixtures(): array
    {
        return [
            ['slug' => 'appartement', 'name' => 'Appartement', 'text' => 'Notre agence vous propose une selection d appartements a vendre a Essaouira. Appartements neufs ou de caractere, adaptes a tous les projets.'],
            ['slug' => 'riad', 'name' => 'Riad', 'text' => 'Notre agence vous propose une selection de riads a vendre a Essaouira. Decouvrez des biens de charme dans la medina, renoves ou a renover, avec accompagnement complet dans vos demarches d achat.'],
            ['slug' => 'maisons-dhotes', 'name' => 'Maison d hotes', 'text' => 'Maisons d hotes a vendre a Essaouira avec potentiel de rentabilite.'],
            ['slug' => 'villa-golf', 'name' => 'Villa golf', 'text' => 'Le marche des villas sur le golf de Mogador est specifique, avec peu de biens disponibles. Certaines villas offrent des vues mer exceptionnelles et un cadre haut de gamme.'],
            ['slug' => 'maison-de-campagne', 'name' => 'Maison de campagne', 'text' => 'Selection de maisons de campagne a vendre a Essaouira et sa region. Accompagnement dans toutes les etapes de votre projet immobilier.'],
            ['slug' => 'maison-de-ville', 'name' => 'Maison de ville'],
            ['slug' => 'terrain', 'name' => 'Terrain', 'text' => 'Les terrains a Essaouira peuvent etre situes en zone urbaine ou rurale. Des regles specifiques s appliquent selon la localisation et le type de projet.'],
            ['slug' => 'affaires-commerciales', 'name' => 'Affaire commerciale', 'text' => 'Fonds de commerce a vendre a Essaouira : restaurants, hotels et maisons d hotes.'],
            ['slug' => 'location-gerance', 'name' => 'Location gerance', 'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.'],
            ['slug' => 'local-commercial', 'name' => 'Local commercial', 'text' => 'Vente Locaux de commerce Essaouira : selection de fonds de commerce a vendre a Essaouira et sa region. Nos specialistes vous accompagnent pour trouver l affaire adaptee a votre projet.'],
            ['slug' => 'dar', 'name' => 'Dar', 'text' => 'Selection de dars a vendre a Essaouira.'],
            ['slug' => 'chambre-dhotes', 'name' => 'Chambre d hotes'],
        ];
    }

    /**
     * @return list<array{slug: string, name: string, description?: string}>
     */
    private function accommodationPlaceFixtures(): array
    {
        return [
            ['slug' => 'medina', 'name' => 'Medina', 'description' => 'Construite a la fin du XVIIIe siecle, la Medina est le coeur historique d Essaouira, animee par ses souks, ses remparts et son port. Classee au patrimoine mondial de l UNESCO, elle seduit par son authenticite et son architecture unique.'],
            ['slug' => 'centre-ville', 'name' => 'Centre-ville'],
            ['slug' => 'marina', 'name' => 'Marina'],
            ['slug' => 'nouvelle-ville', 'name' => 'Nouvelle-ville', 'description' => 'Quartier moderne d Essaouira regroupant commerces, services et habitations recentes, ideal pour un quotidien pratique a proximite du centre.'],
            ['slug' => 'corniche', 'name' => 'Corniche'],
            ['slug' => 'golf-mogador', 'name' => 'Mogador', 'description' => 'Situe a proximite de Diabat, le golf de Mogador est un domaine repute offrant un cadre securise avec villas haut de gamme, entre ocean et nature.'],
            ['slug' => 'palmeraie', 'name' => 'Palmeraie'],
            ['slug' => 'campagne', 'name' => 'Campagne', 'description' => 'Environnement calme autour d Essaouira, ideal pour profiter d espaces naturels, de tranquillite et de proprietes avec terrain.'],
            ['slug' => 'campagne-d-essaouira', 'name' => 'Campagne d Essaouira'],
            ['slug' => 'diabet', 'name' => 'Diabet', 'description' => 'Situe a l ouest d Essaouira, Diabat est un village emblematique connu pour ses dunes, son ambiance boheme et sa proximite immediate avec la plage. Tres prise pour les residences secondaires et les maisons de charme, le secteur beneficie egalement de la presence du golf de Mogador a proximite. Diabat seduit par son atmosphere paisible, son authenticite et son cadre naturel unique entre ocean et desert.'],
            ['slug' => 'ghazoua', 'name' => 'Ghazoua', 'description' => 'Situee a quelques minutes d Essaouira, Ghazoua est une zone residentielle en plein developpement, tres recherchee pour ses villas avec jardin et piscine. Le secteur offre un bon compromis entre calme, accessibilite et proximite des commodites. On y trouve de nombreuses proprietes modernes ainsi que des maisons d hotes, dans un environnement paisible a seulement quelques minutes du centre-ville et des plages.'],
            ['slug' => 'boulevard-principal', 'name' => 'Boulevard principal'],
            ['slug' => 'douar-larab', 'name' => 'Douar Laarab', 'description' => 'Douar situe dans l arriere-pays d Essaouira, offrant un cadre authentique et rural, apprecie pour son calme et ses espaces naturels.'],
            ['slug' => 'sidi-kaouki', 'name' => 'Sidi Kaouki', 'description' => 'A une trentaine de minutes au sud d Essaouira, Sidi Kaouki est une destination reputee pour ses plages sauvages et son cadre naturel preserve. Tres apprecie des amateurs de surf et de tranquillite, le secteur attire une clientele en quete d evasion et d authenticite. L immobilier y est oriente vers des maisons de charme, des villas et des projets touristiques dans un environnement encore preserve.'],
            ['slug' => 'ida-ougourd', 'name' => 'Ida Ougourd', 'description' => 'Zone rurale situee a proximite d Essaouira, appreciee pour son authenticite, son calme et ses paysages naturels.'],
            ['slug' => 'hrarta', 'name' => 'Hrarta', 'description' => 'Secteur en developpement autour d Essaouira, offrant un cadre paisible entre ville et campagne, adapte aux projets immobiliers.'],
            ['slug' => 'bouzama', 'name' => 'Bouzama', 'description' => 'Village situe aux alentours d Essaouira, offrant un environnement calme et authentique, propice a la vie en pleine nature.'],
            ['slug' => 'aeroport', 'name' => 'Route de l aeroport', 'description' => 'Zone reliant Essaouira a son aeroport, appreciee pour son accessibilite et son potentiel de developpement.'],
            ['slug' => 'had-draa', 'name' => 'Had Draa', 'description' => 'Localite rurale proche d Essaouira, connue pour son ambiance traditionnelle et ses marches locaux.'],
            ['slug' => 'ounagha', 'name' => 'Ounagha', 'description' => 'Commune situee a proximite d Essaouira, combinant accessibilite, authenticite et cadre naturel agreable.'],
            ['slug' => 'moulay-bouzerktoun', 'name' => 'Moulay Bouzerktoun', 'description' => 'Spot repute pour le kitesurf et le windsurf, attirant les amateurs de sports nautiques et de nature.'],
            ['slug' => 'laraich', 'name' => 'Laraiche', 'description' => 'Zone rurale aux environs d Essaouira, offrant un cadre calme et authentique, ideal pour des projets residentiels.'],
            ['slug' => 'chicht', 'name' => 'Chicht', 'description' => 'Village situe dans la region d Essaouira, proposant un environnement paisible et typique de la campagne marocaine.'],
            ['slug' => 'route-de-marrakech', 'name' => 'Route de Marrakech', 'description' => 'Axe majeur reliant Essaouira a Marrakech, offrant un bon compromis entre accessibilite et opportunites immobilieres.'],
            ['slug' => 'route-d-agadir', 'name' => 'Route d Agadir'],
            ['slug' => 'route-de-safi', 'name' => 'Route de Safi', 'description' => 'Secteur desservi par la route de Safi, apprecie pour son accessibilite et ses possibilites de developpement immobilier.'],
            ['slug' => 'zone-rurale', 'name' => 'Zone rurale', 'description' => 'Secteurs situes en dehors du centre urbain, offrant calme, espace et cadre naturel autour d Essaouira.'],
            ['slug' => 'zone-urbaine', 'name' => 'Zone urbaine', 'description' => 'Zones situees au coeur ou a proximite immediate d Essaouira, offrant un acces direct aux commerces, services et infrastructures.'],
        ];
    }

    /**
     * @return list<array{codeValue: string, name: string, inCodeSet: string}>
     */
    private function genericCategoryCodeFixtures(): array
    {
        return [
            ['codeValue' => 'acces-route', 'name' => 'Acces route', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'accessibilite', 'name' => 'Accessibilite', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'agence-immobiliere', 'name' => 'Agence immobiliere', 'inCodeSet' => 'article-section'],
            ['codeValue' => 'air-conditionne', 'name' => 'Air conditionne', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'alarme', 'name' => 'Alarme', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'arganier', 'name' => 'Arganier', 'inCodeSet' => 'tag'],
            ['codeValue' => 'ascenseur', 'name' => 'Ascenseur', 'inCodeSet' => 'tag'],
            ['codeValue' => 'atelier', 'name' => 'Atelier', 'inCodeSet' => 'tag'],
            ['codeValue' => 'balcon', 'name' => 'Balcon', 'inCodeSet' => 'tag'],
            ['codeValue' => 'bien-titre', 'name' => 'Bien titre', 'inCodeSet' => 'tag'],
            ['codeValue' => 'boutique', 'name' => 'Boutique', 'inCodeSet' => 'tag'],
            ['codeValue' => 'campagne', 'name' => 'Campagne', 'inCodeSet' => 'tag'],
            ['codeValue' => 'centre', 'name' => 'Centre', 'inCodeSet' => 'tag'],
            ['codeValue' => 'centre-ville', 'name' => 'Centre Ville', 'inCodeSet' => 'tag'],
            ['codeValue' => 'chateau-d-eau', 'name' => 'Chateau d eau', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'cheminee', 'name' => 'Cheminee', 'inCodeSet' => 'tag'],
            ['codeValue' => 'climatisation', 'name' => 'Climatisation', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'climatisation-reversible', 'name' => 'Climatisation reversible', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'commerce', 'name' => 'Commerce', 'inCodeSet' => 'tag'],
            ['codeValue' => 'contemporaine', 'name' => 'Contemporaine', 'inCodeSet' => 'tag'],
            ['codeValue' => 'exposition-sud', 'name' => 'Exposition Sud', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'essaouira', 'name' => 'Essaouira', 'inCodeSet' => 'tag'],
            ['codeValue' => 'garage', 'name' => 'Garage', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'gardien', 'name' => 'Gardien', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'gestion-locative', 'name' => 'Gestion locative', 'inCodeSet' => 'tag'],
            ['codeValue' => 'golf', 'name' => 'Golf', 'inCodeSet' => 'tag'],
            ['codeValue' => 'hammam', 'name' => 'Hammam', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'hotel', 'name' => 'Hotel', 'inCodeSet' => 'tag'],
            ['codeValue' => 'immobilier', 'name' => 'Immobilier', 'inCodeSet' => 'tag'],
            ['codeValue' => 'investissement', 'name' => 'Investissement', 'inCodeSet' => 'tag'],
            ['codeValue' => 'jacuzzi', 'name' => 'Jacuzzi', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'jardin', 'name' => 'Jardin', 'inCodeSet' => 'tag'],
            ['codeValue' => 'marina', 'name' => 'Marina', 'inCodeSet' => 'tag'],
            ['codeValue' => 'medina', 'name' => 'Medina', 'inCodeSet' => 'tag'],
            ['codeValue' => 'mezzanine', 'name' => 'Mezzanine', 'inCodeSet' => 'tag'],
            ['codeValue' => 'parking', 'name' => 'Parking', 'inCodeSet' => 'tag'],
            ['codeValue' => 'patio', 'name' => 'Patio', 'inCodeSet' => 'tag'],
            ['codeValue' => 'petit-dejeuner', 'name' => 'Petit Dejeuner', 'inCodeSet' => 'tag'],
            ['codeValue' => 'piscine', 'name' => 'Piscine', 'inCodeSet' => 'tag'],
            ['codeValue' => 'piscine-chauffee', 'name' => 'Piscine chauffee', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'riad', 'name' => 'Riad', 'inCodeSet' => 'tag'],
            ['codeValue' => 'room-service', 'name' => 'Room service', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'route-de-safi', 'name' => 'Route De Safi', 'inCodeSet' => 'tag'],
            ['codeValue' => 'salle-de-bain', 'name' => 'Salle de bain', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'salle-de-sport', 'name' => 'Salle de sport', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'sidi-kaouki', 'name' => 'Sidi Kaouki', 'inCodeSet' => 'tag'],
            ['codeValue' => 'solarium', 'name' => 'Solarium', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'spa', 'name' => 'Spa', 'inCodeSet' => 'tag'],
            ['codeValue' => 'terrain', 'name' => 'Terrain', 'inCodeSet' => 'tag'],
            ['codeValue' => 'terrasse', 'name' => 'Terrasse', 'inCodeSet' => 'tag'],
            ['codeValue' => 'terrasse-attenante', 'name' => 'Terrasse attenante', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'toit-terrasse', 'name' => 'Toit Terrasse', 'inCodeSet' => 'tag'],
            ['codeValue' => 'viabilise', 'name' => 'Viabilise', 'inCodeSet' => 'tag'],
            ['codeValue' => 'vitrine', 'name' => 'Vitrine', 'inCodeSet' => 'tag'],
            ['codeValue' => 'vue-degagee', 'name' => 'Vue degagee', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'vue-mer', 'name' => 'Vue mer', 'inCodeSet' => 'tag'],
            ['codeValue' => 'vue-ocean', 'name' => 'Vue Ocean', 'inCodeSet' => 'tag'],
            ['codeValue' => 'vue-sur-mer', 'name' => 'Vue sur mer', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'wifi', 'name' => 'Wifi', 'inCodeSet' => 'amenity-feature'],
            ['codeValue' => 'erraounak', 'name' => 'Erraounak', 'inCodeSet' => 'tag'],
            ['codeValue' => 'exploitation-commerciale', 'name' => 'Exploitation commerciale', 'inCodeSet' => 'amenity-feature'],
        ];
    }

    /**
     * @return list<array{slug: string, name: string, text?: string, isActive?: bool}>
     */
    private function realEstateListingFixtures(): array
    {
        return [
            ['slug' => 'bien-a-vendre', 'name' => 'Biens a vendre', 'text' => 'Decouvrez notre selection de maisons, riads et villas a vendre a Essaouira. Que vous recherchiez une residence principale, secondaire ou un investissement locatif, nos biens sont selectionnes pour leur emplacement et leur potentiel.'],
            ['slug' => 'location-saisonniere', 'name' => 'Locations saisonniere', 'text' => 'Profitez d une experience unique grace a nos locations saisonnieres a Essaouira. Des biens confortables et authentiques, idealement situes pour decouvrir la medina et le littoral.'],
            ['slug' => 'location-longue-duree', 'name' => 'Locations longue duree', 'text' => 'Nous proposons egalement des biens en location longue duree, adaptes a une installation durable dans la region, avec des options variees selon vos besoins.'],
            ['slug' => 'professionnels', 'name' => 'Professionnels', 'isActive' => false, 'text' => 'Explorez nos opportunites pour les professionnels : locaux commerciaux, maisons d hotes, projets touristiques ou investissements a fort potentiel.'],
        ];
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

        $accommodation->ensureDefaultQualities();

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

    /**
     * @param array<string, mixed> $metadata
     */
    private function upsertCategoryCode(Project $project, string $inCodeSet, string $code, string $label, User $actor, array $metadata = []): CategoryCode
    {
        $cacheKey = (string) $project->getId().':'.$inCodeSet.':'.$code;
        if (isset($this->categoryCodeCache[$cacheKey])) {
            $category = $this->categoryCodeCache[$cacheKey];
            $category->setCurrentLocale('fr');
            $category
                ->setLabel($label)
                ->setMetadata(['fixture' => 'demo', ...$metadata])
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
            ->setMetadata(['fixture' => 'demo', ...$metadata])
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
