<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Accommodation;
use App\Entity\AccommodationMedia;
use App\Entity\CategoryCode;
use App\Entity\MediaObject;
use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\PropertyValue;
use App\Entity\User;
use App\Entity\WebPage;
use App\Enum\AccommodationQuality;
use App\Enum\MediaStatus;
use App\Enum\OrganizationRole;
use App\Enum\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:fixtures:load-test',
    description: 'Load minimal deterministic fixtures for functional tests.',
)]
final class LoadTestFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $admin = $this->createUser('admin@grainesdigitales.local');
        $editor = $this->createUser('editor.essaouira@grainesdigitales.local');
        $viewer = $this->createUser('viewer.mogador@grainesdigitales.local');
        $this->createUser('johan.remy@graines-digitales.online');

        $this->entityManager->flush();

        $epi = $this->createOrganization('Essaouira Patrimoine Immobilier', 'essaouira-patrimoine-immobilier', $admin);
        $acl = $this->createOrganization('Atlas Coastal Living', 'atlas-coastal-living', $admin);

        $this->entityManager->flush();

        $riads = $this->createProject('Riads Médina Essaouira', 'riads-medina-essaouira', $epi, $admin);
        $bbm = $this->createProject('Blue Bay Mogador', 'blue-bay-mogador', $epi, $admin);
        $villas = $this->createProject('Villas Arganier Ghazoua', 'villas-arganier-ghazoua', $acl, $admin);

        $this->entityManager->flush();

        $this->createProjectMember($riads, $editor, ProjectRole::Editor, $admin);
        $this->createProjectMember($villas, $editor, ProjectRole::Editor, $admin);
        $this->createProjectMember($bbm, $viewer, ProjectRole::Viewer, $admin);

        $this->entityManager->flush();

        $this->seedRiadsProject($riads, $admin);
        $this->seedVillasProject($villas, $admin);
        $this->seedBbmProject($bbm, $admin);

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function seedRiadsProject(Project $project, User $admin): void
    {
        $type = $this->createCategoryCode($project, 'accommodation-type', 'riad', 'Riad', $admin);
        $listing = $this->createCategoryCode($project, 'real-estate-listing', 'bien-a-vendre', 'Bien à vendre', $admin);
        $place = $this->createCategoryCode($project, 'accommodation-place', 'nouvelle-ville', 'Nouvelle ville', $admin);
        $amenity1 = $this->createCategoryCode($project, 'amenity-feature', 'piscine', 'Piscine', $admin);
        $amenity2 = $this->createCategoryCode($project, 'amenity-feature', 'jardin', 'Jardin', $admin);
        $amenity3 = $this->createCategoryCode($project, 'amenity-feature', 'terrasse', 'Terrasse', $admin);
        $tag1 = $this->createCategoryCode($project, 'tag', 'calme', 'Calme', $admin);
        $tag2 = $this->createCategoryCode($project, 'tag', 'vue-mer', 'Vue mer', $admin);
        $tag3 = $this->createCategoryCode($project, 'tag', 'medina', 'Médina', $admin);

        $media = $this->createMediaObject($project, 'riad-principal.jpg', $admin);
        $media2 = $this->createMediaObject($project, 'riad-secondaire.jpg', $admin);
        $this->createWebPage($project, 'accueil', 'Accueil', 'Bienvenue dans les riads de la médina.', $admin);
        $this->createWebPage($project, 'accueil-suite', 'Accueil Suite', 'Découvrez nos riads.', $admin);

        $this->createAccommodation(
            $project,
            'BAVR001',
            'Riad Salam',
            'riad-salam',
            $type,
            $listing,
            $place,
            [$amenity1, $amenity2, $amenity3],
            [$tag1, $tag2, $tag3],
            $media,
            $admin,
        );

        $this->createAccommodation(
            $project,
            'BAVR002',
            'Riad Lumière',
            'riad-lumiere',
            $type,
            $listing,
            $place,
            [$amenity1, $amenity2],
            [$tag1, $tag3],
            $media2,
            $admin,
        );
    }

    private function seedVillasProject(Project $project, User $admin): void
    {
        $type = $this->createCategoryCode($project, 'accommodation-type', 'local-commercial', 'Local commercial', $admin);
        $listing = $this->createCategoryCode($project, 'real-estate-listing', 'bien-a-vendre', 'Bien à vendre', $admin);
        $place = $this->createCategoryCode($project, 'accommodation-place', 'nouvelle-ville', 'Nouvelle ville', $admin);
        $amenity1 = $this->createCategoryCode($project, 'amenity-feature', 'vue-degagee', 'Vue dégagée', $admin);
        $amenity2 = $this->createCategoryCode($project, 'amenity-feature', 'parking', 'Parking', $admin);
        $amenity3 = $this->createCategoryCode($project, 'amenity-feature', 'vitrine', 'Vitrine', $admin);
        $tag1 = $this->createCategoryCode($project, 'tag', 'commerce', 'Commerce', $admin);
        $tag2 = $this->createCategoryCode($project, 'tag', 'artisanat', 'Artisanat', $admin);
        $tag3 = $this->createCategoryCode($project, 'tag', 'centre-ville', 'Centre-ville', $admin);

        $media = $this->createMediaObject($project, 'local-commercial-erraounak.jpg', $admin);
        $this->createWebPage($project, 'accueil-villas', 'Accueil Villas', 'Villas et locaux à Ghazoua.', $admin);

        $this->createAccommodation(
            $project,
            'BAVLC001',
            'Superbe local commercial a Erraounak',
            'local-commercial-erraounak',
            $type,
            $listing,
            $place,
            [$amenity1, $amenity2, $amenity3],
            [$tag1, $tag2, $tag3],
            $media,
            $admin,
        );
    }

    private function seedBbmProject(Project $project, User $admin): void
    {
        $type = $this->createCategoryCode($project, 'accommodation-type', 'villa', 'Villa', $admin);
        $listing = $this->createCategoryCode($project, 'real-estate-listing', 'location', 'Location', $admin);
        $place = $this->createCategoryCode($project, 'accommodation-place', 'bord-de-mer', 'Bord de mer', $admin);
        $amenity1 = $this->createCategoryCode($project, 'amenity-feature', 'piscine', 'Piscine', $admin);
        $amenity2 = $this->createCategoryCode($project, 'amenity-feature', 'terrasse', 'Terrasse', $admin);
        $amenity3 = $this->createCategoryCode($project, 'amenity-feature', 'barbecue', 'Barbecue', $admin);
        $tag1 = $this->createCategoryCode($project, 'tag', 'balnéaire', 'Balnéaire', $admin);
        $tag2 = $this->createCategoryCode($project, 'tag', 'familial', 'Familial', $admin);
        $tag3 = $this->createCategoryCode($project, 'tag', 'luxe', 'Luxe', $admin);

        $media = $this->createMediaObject($project, 'villa-blue-bay.jpg', $admin);
        $this->createWebPage($project, 'accueil-bbm', 'Accueil Blue Bay', 'Bienvenue à Blue Bay Mogador.', $admin);

        $this->createAccommodation(
            $project,
            'FBAVVG001',
            'Villa Blue Bay',
            'villa-blue-bay',
            $type,
            $listing,
            $place,
            [$amenity1, $amenity2, $amenity3],
            [$tag1, $tag2, $tag3],
            $media,
            $admin,
        );
    }

    private function createUser(string $email): User
    {
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $this->entityManager->persist($user);

        return $user;
    }

    private function createOrganization(string $name, string $slug, User $owner): Organization
    {
        $existing = $this->entityManager->getRepository(Organization::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Organization) {
            return $existing;
        }

        $org = new Organization();
        $org->setName($name);
        $org->setSlug($slug);
        $this->entityManager->persist($org);

        $member = new OrganizationMember();
        $member->setOrganization($org);
        $member->setUser($owner);
        $member->setRole(OrganizationRole::Owner);
        $member->markCreatedBy($owner);
        $member->touch($owner);
        $this->entityManager->persist($member);

        return $org;
    }

    private function createProject(string $name, string $slug, Organization $org, User $owner): Project
    {
        $existing = $this->entityManager->getRepository(Project::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Project) {
            return $existing;
        }

        $project = new Project();
        $project->setName($name);
        $project->setSlug($slug);
        $project->setOrganization($org);
        $project->setSourceLocale('fr');
        $project->setEnabledLocales(['fr', 'en', 'es']);
        $project->markCreatedBy($owner);
        $project->touch($owner);
        $this->entityManager->persist($project);

        $this->createProjectMember($project, $owner, ProjectRole::Owner, $owner);

        return $project;
    }

    private function createProjectMember(Project $project, User $user, ProjectRole $role, User $actor): void
    {
        $existing = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);

        if ($existing instanceof ProjectMember) {
            return;
        }

        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($role);
        $member->markCreatedBy($actor);
        $member->touch($actor);
        $this->entityManager->persist($member);
    }

    private function createCategoryCode(
        Project $project,
        string $inCodeSet,
        string $code,
        string $label,
        User $actor,
    ): CategoryCode {
        $existing = $this->entityManager->getRepository(CategoryCode::class)->findOneBy([
            'project' => $project,
            'inCodeSet' => $inCodeSet,
            'code' => $code,
        ]);

        if ($existing instanceof CategoryCode) {
            return $existing;
        }

        $cc = new CategoryCode();
        $cc->setProject($project);
        $cc->setInCodeSet($inCodeSet);
        $cc->setCodeValue($code);
        $cc->setCurrentLocale('fr');
        $cc->setLabel($label);
        $cc->markCreatedBy($actor);
        $cc->touch($actor);
        $this->entityManager->persist($cc);

        return $cc;
    }

    private function createMediaObject(Project $project, string $filename, User $actor): MediaObject
    {
        $existing = $this->entityManager->getRepository(MediaObject::class)->findOneBy([
            'project' => $project,
            'originalFilename' => $filename,
        ]);

        if ($existing instanceof MediaObject) {
            return $existing;
        }

        $media = new MediaObject();
        $media->setProject($project);
        $media->setOriginalFilename($filename);
        $media->setStatus(MediaStatus::Ready);
        $media->markCreatedBy($actor);
        $media->touch($actor);
        $this->entityManager->persist($media);

        return $media;
    }

    private function createWebPage(
        Project $project,
        string $slug,
        string $headline,
        string $body,
        User $actor,
    ): WebPage {
        $existing = $this->entityManager->createQueryBuilder()
            ->select('wp')
            ->from(WebPage::class, 'wp')
            ->join('wp.translations', 't')
            ->where('wp.project = :project')
            ->andWhere('wp.deletedAt IS NULL')
            ->andWhere('t.slug = :slug')
            ->setParameter('project', $project)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof WebPage) {
            return $existing;
        }

        $page = new WebPage();
        $page->setProject($project);
        $page->setCurrentLocale('fr');
        $page->setSlug($slug);
        $page->setHeadline($headline);
        $page->setBody($body);
        $page->markCreatedBy($actor);
        $page->touch($actor);
        $this->entityManager->persist($page);

        return $page;
    }

    /**
     * @param CategoryCode[] $amenityFeatures
     * @param CategoryCode[] $tags
     */
    private function createAccommodation(
        Project $project,
        string $identifier,
        string $name,
        string $slug,
        CategoryCode $category,
        CategoryCode $realEstateListing,
        CategoryCode $place,
        array $amenityFeatures,
        array $tags,
        MediaObject $mediaObject,
        User $actor,
    ): Accommodation {
        $existing = $this->entityManager->getRepository(Accommodation::class)->findOneBy([
            'project' => $project,
            'identifier' => $identifier,
        ]);

        if ($existing instanceof Accommodation) {
            return $existing;
        }

        $accommodation = new Accommodation();
        $accommodation->setProject($project);
        $accommodation->setIdentifier($identifier);
        $accommodation->setCategory($category);
        $accommodation->setRealEstateListing($realEstateListing);
        $accommodation->setPlace($place);
        $accommodation->setCurrentLocale('fr');
        $accommodation->setName($name);
        $accommodation->setSlug($slug);
        $accommodation->markCreatedBy($actor);
        $accommodation->touch($actor);

        foreach ($amenityFeatures as $amenity) {
            $accommodation->addAmenityFeature($amenity);
        }

        foreach ($tags as $tag) {
            $accommodation->addTag($tag);
        }

        foreach (AccommodationQuality::cases() as $quality) {
            $pv = (new PropertyValue())->setName($quality)->setValue(0);
            $accommodation->addQuality($pv);
            $this->entityManager->persist($pv);
        }

        $media = new AccommodationMedia();
        $media->setMediaObject($mediaObject);
        $media->setPosition(1);
        $accommodation->addAssociatedMedia($media);
        $this->entityManager->persist($media);

        $this->entityManager->persist($accommodation);

        return $accommodation;
    }
}
