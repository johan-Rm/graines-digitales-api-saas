<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Attribute\InCodeSet;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\ProjectOwned;
use App\Entity\Traits\TranslatableResourceBehavior;
use App\Entity\Traits\UuidIdentity;
use App\Enum\AccommodationQuality;
use App\Enum\ContentStatus;
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedAccommodationBulkTranslationProcessor;
use App\State\ProjectScopedAccommodationCreateWithTranslationsProcessor;
use App\State\ProjectScopedAccommodationPersistProcessor;
use App\State\ProjectScopedAccommodationRemoveProcessor;
use App\State\ProjectScopedPersistProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_accommodation_project')]
#[ORM\Index(columns: ['project_id', 'status', 'is_active'], name: 'idx_accommodation_project_publication')]
#[ORM\UniqueConstraint(name: 'uniq_accommodation_project_identifier', columns: ['project_id', 'identifier'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/accommodations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/accommodations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], read: false, processor: ProjectScopedAccommodationPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/accommodations/{id}', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: Accommodation::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class),
        new Get(uriTemplate: '/projects/{projectId}/accommodations/{id}/translations', name: 'get_translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: Accommodation::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class, normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Put(uriTemplate: '/projects/{projectId}/accommodations/{id}', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: Accommodation::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class, processor: ProjectScopedAccommodationPersistProcessor::class, denormalizationContext: ['groups' => ['write'], 'api_assign_object_to_populate' => true]),
        new Put(uriTemplate: '/projects/{projectId}/accommodations/{id}/translations', name: 'put_translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: Accommodation::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class, processor: ProjectScopedAccommodationBulkTranslationProcessor::class, denormalizationContext: ['groups' => ['write:translations'], 'api_assign_object_to_populate' => true], normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Post(uriTemplate: '/projects/{projectId}/accommodations/translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], name: 'post_accommodation_translations', read: false, processor: ProjectScopedAccommodationCreateWithTranslationsProcessor::class, normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Delete(uriTemplate: '/projects/{projectId}/accommodations/{id}', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: Accommodation::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class, processor: ProjectScopedAccommodationRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class Accommodation implements ProjectScopedResource, TranslatableResource
{
    public const CATEGORY_CODE_SET = 'accommodation-type';
    public const REAL_ESTATE_LISTING_CODE_SET = 'real-estate-listing';
    public const PLACE_CODE_SET = 'accommodation-place';
    public const AMENITY_FEATURE_CODE_SET = 'amenity-feature';
    public const TAG_CODE_SET = 'tag';

    use AuditTrail;
    use ProjectOwned;
    use TranslatableResourceBehavior;
    use UuidIdentity;

    #[ORM\Column(length: 180)]
    #[Groups(['read'])]
    private string $identifier = '';

    #[ORM\ManyToOne(targetEntity: CategoryCode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    #[Groups(['read', 'write'])]
    #[InCodeSet(self::CATEGORY_CODE_SET)]
    private CategoryCode $category;

    #[ORM\ManyToOne(targetEntity: CategoryCode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    #[Groups(['read', 'write'])]
    #[InCodeSet(self::REAL_ESTATE_LISTING_CODE_SET)]
    private CategoryCode $realEstateListing;

    #[ORM\ManyToOne(targetEntity: CategoryCode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    #[Groups(['read', 'write'])]
    #[InCodeSet(self::PLACE_CODE_SET)]
    private CategoryCode $place;

    /**
     * @var Collection<int, CategoryCode>
     */
    #[ORM\ManyToMany(targetEntity: CategoryCode::class)]
    #[ORM\JoinTable(name: 'accommodation_amenity_feature')]
    #[Groups(['read', 'write'])]
    #[InCodeSet(self::AMENITY_FEATURE_CODE_SET)]
    private Collection $amenityFeature;

    /**
     * @var Collection<int, CategoryCode>
     */
    #[ORM\ManyToMany(targetEntity: CategoryCode::class)]
    #[ORM\JoinTable(name: 'accommodation_tag')]
    #[Groups(['read', 'write'])]
    #[InCodeSet(self::TAG_CODE_SET)]
    private Collection $tags;

    /**
     * @var Collection<int, AccommodationMedia>
     */
    #[ORM\OneToMany(mappedBy: 'accommodation', targetEntity: AccommodationMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['read', 'write'])]
    private Collection $associatedMedia;

    /**
     * @var Collection<int, PropertyValue>
     */
    #[ORM\OneToMany(mappedBy: 'accommodation', targetEntity: PropertyValue::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['read', 'write'])]
    private Collection $qualities;

    /**
     * @var Collection<int, AccommodationTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: AccommodationTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['read'])]
    private Collection $translations;

    #[ORM\Column(enumType: ContentStatus::class)]
    #[Groups(['read', 'write'])]
    private ContentStatus $status = ContentStatus::Draft;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $yearBuilt = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $areaSize = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $areaTerrace = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $floorSize = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $landArea = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $level = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $numberOfRooms = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $numberOfBedrooms = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $numberOfBathroomsTotal = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $numberOfGarages = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $occupancy = null;

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private bool $isActive = true;

    /**
     * @var array{price?: string|null, priceCurrency?: string|null, priceSpecification?: string|null, availability?: string|null}
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $offer = [];

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->amenityFeature = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->associatedMedia = new ArrayCollection();
        $this->qualities = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

    public function getIdentifier(): string { return $this->identifier; }
    public function setIdentifier(string $identifier): self { $this->identifier = $identifier; return $this; }
    public function hasCategory(): bool { return isset($this->category); }
    public function getCategory(): CategoryCode { return $this->category ?? throw new \LogicException('Accommodation category is required.'); }
    public function setCategory(CategoryCode $category): self { $this->category = $category; return $this; }
    public function hasRealEstateListing(): bool { return isset($this->realEstateListing); }
    public function getRealEstateListing(): CategoryCode { return $this->realEstateListing ?? throw new \LogicException('Accommodation real estate listing is required.'); }
    public function setRealEstateListing(CategoryCode $realEstateListing): self { $this->realEstateListing = $realEstateListing; return $this; }
    public function hasPlace(): bool { return isset($this->place); }
    public function getPlace(): CategoryCode { return $this->place ?? throw new \LogicException('Accommodation place is required.'); }
    public function setPlace(CategoryCode $place): self { $this->place = $place; return $this; }
    public function getAmenityFeature(): Collection { return $this->amenityFeature; }
    public function addAmenityFeature(CategoryCode $amenityFeature): self { if (!$this->amenityFeature->contains($amenityFeature)) { $this->amenityFeature->add($amenityFeature); } return $this; }
    public function removeAmenityFeature(CategoryCode $amenityFeature): self { $this->amenityFeature->removeElement($amenityFeature); return $this; }
    public function clearAmenityFeature(): self { $this->amenityFeature->clear(); return $this; }
    public function getTags(): Collection { return $this->tags; }
    public function addTag(CategoryCode $tag): self { if (!$this->tags->contains($tag)) { $this->tags->add($tag); } return $this; }
    public function removeTag(CategoryCode $tag): self { $this->tags->removeElement($tag); return $this; }
    public function clearTags(): self { $this->tags->clear(); return $this; }
    public function getAssociatedMedia(): Collection { return $this->associatedMedia; }
    public function addAssociatedMedia(AccommodationMedia $media): self { if (!$this->associatedMedia->contains($media)) { $this->associatedMedia->add($media); $media->setAccommodation($this); } return $this; }
    public function addAssociatedMedium(AccommodationMedia $media): self { return $this->addAssociatedMedia($media); }
    public function removeAssociatedMedia(AccommodationMedia $media): self { $this->associatedMedia->removeElement($media); return $this; }
    public function removeAssociatedMedium(AccommodationMedia $media): self { return $this->removeAssociatedMedia($media); }
    public function clearAssociatedMedia(): self { $this->associatedMedia->clear(); return $this; }
    public function getQualities(): Collection { return $this->qualities; }
    public function addQuality(PropertyValue $quality): self { if (!$this->qualities->contains($quality)) { $quality->setPosition($this->qualities->count() + 1); $this->qualities->add($quality); $quality->setAccommodation($this); } return $this; }
    public function removeQuality(PropertyValue $quality): self { $this->qualities->removeElement($quality); return $this; }
    public function clearQualities(): self { $this->qualities->clear(); return $this; }
    public function ensureDefaultQualities(): self
    {
        if (!$this->qualities->isEmpty()) {
            return $this;
        }

        foreach (AccommodationQuality::cases() as $name) {
            $this->addQuality((new PropertyValue())->setName($name)->setValue(0));
        }

        return $this;
    }
    protected function createTranslation(): ResourceTranslation { return new AccommodationTranslation(); }
    protected function addTranslation(ResourceTranslation $translation): self { if (!$this->translations->contains($translation)) { $this->translations->add($translation); } return $this; }
    public function getTranslations(): Collection { return $this->translations; }
    #[Groups(['read'])]
    public function getRequestedLocale(): string { return $this->requestedLocale; }
    #[Groups(['read'])]
    public function getLocale(): string { return $this->currentLocale; }
    #[Groups(['read'])]
    public function getFallbackLocale(): ?string { return $this->fallbackLocale; }
    #[Groups(['read'])]
    public function isFallback(): bool { return $this->fallback; }
    #[Groups(['read'])]
    public function getSlug(): string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getSlug() : ''; }
    public function setSlug(string $slug): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setSlug($slug); } return $this; }
    #[Groups(['read', 'write'])]
    public function getName(): ?string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getName() : null; }
    public function setName(?string $name): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setName($name); } return $this; }
    #[Groups(['read', 'write'])]
    public function getLabel(): ?string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getLabel() : null; }
    public function setLabel(?string $label): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setLabel($label); } return $this; }
    #[Groups(['read', 'write'])]
    public function getHighlight(): ?string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getHighlight() : null; }
    public function setHighlight(?string $highlight): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setHighlight($highlight); } return $this; }
    #[Groups(['read', 'write'])]
    public function getBody(): ?string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getBody() : null; }
    public function setBody(?string $body): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setBody($body); } return $this; }
    #[Groups(['read', 'write'])]
    public function getReview(): ?string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getReview() : null; }
    public function setReview(?string $review): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setReview($review); } return $this; }
    #[Groups(['read', 'write'])]
    public function getMetaTitle(): ?string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getMetaTitle() : null; }
    public function setMetaTitle(?string $metaTitle): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setMetaTitle($metaTitle); } return $this; }
    #[Groups(['read', 'write'])]
    public function getMetaDescription(): ?string { $translation = $this->getTranslation(); return $translation instanceof AccommodationTranslation ? $translation->getMetaDescription() : null; }
    public function setMetaDescription(?string $metaDescription): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof AccommodationTranslation) { $t->setMetaDescription($metaDescription); } return $this; }
    public function getStatus(): ContentStatus { return $this->status; }
    public function setStatus(ContentStatus $status): self { $this->status = $status; return $this; }
    public function getYearBuilt(): ?int { return $this->yearBuilt; }
    public function setYearBuilt(?int $yearBuilt): self { $this->yearBuilt = $yearBuilt; return $this; }
    public function getAreaSize(): ?string { return $this->areaSize; }
    public function setAreaSize(?string $areaSize): self { $this->areaSize = $areaSize; return $this; }
    public function getAreaTerrace(): ?string { return $this->areaTerrace; }
    public function setAreaTerrace(?string $areaTerrace): self { $this->areaTerrace = $areaTerrace; return $this; }
    public function getFloorSize(): ?string { return $this->floorSize; }
    public function setFloorSize(?string $floorSize): self { $this->floorSize = $floorSize; return $this; }
    public function getLandArea(): ?string { return $this->landArea; }
    public function setLandArea(?string $landArea): self { $this->landArea = $landArea; return $this; }
    public function getLevel(): ?int { return $this->level; }
    public function setLevel(?int $level): self { $this->level = $level; return $this; }
    public function getNumberOfRooms(): ?int { return $this->numberOfRooms; }
    public function setNumberOfRooms(?int $numberOfRooms): self { $this->numberOfRooms = $numberOfRooms; return $this; }
    public function getNumberOfBedrooms(): ?int { return $this->numberOfBedrooms; }
    public function setNumberOfBedrooms(?int $numberOfBedrooms): self { $this->numberOfBedrooms = $numberOfBedrooms; return $this; }
    public function getNumberOfBathroomsTotal(): ?int { return $this->numberOfBathroomsTotal; }
    public function setNumberOfBathroomsTotal(?int $numberOfBathroomsTotal): self { $this->numberOfBathroomsTotal = $numberOfBathroomsTotal; return $this; }
    public function getNumberOfGarages(): ?int { return $this->numberOfGarages; }
    public function setNumberOfGarages(?int $numberOfGarages): self { $this->numberOfGarages = $numberOfGarages; return $this; }
    public function getOccupancy(): ?int { return $this->occupancy; }
    public function setOccupancy(?int $occupancy): self { $this->occupancy = $occupancy; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    /**
     * @return array{price?: string|null, priceCurrency?: string|null, priceSpecification?: string|null, availability?: string|null}
     */
    public function getOffer(): array { return $this->offer; }
    /**
     * @param array<string, mixed> $offer
     */
    public function setOffer(array $offer): self
    {
        $this->offer = array_filter([
            'price' => isset($offer['price']) && is_scalar($offer['price']) ? (string) $offer['price'] : null,
            'priceCurrency' => isset($offer['priceCurrency']) && is_scalar($offer['priceCurrency']) ? (string) $offer['priceCurrency'] : null,
            'priceSpecification' => isset($offer['priceSpecification']) && is_scalar($offer['priceSpecification']) ? (string) $offer['priceSpecification'] : null,
            'availability' => isset($offer['availability']) && is_scalar($offer['availability']) ? (string) $offer['availability'] : null,
        ], static fn (?string $value): bool => null !== $value && '' !== $value);

        return $this;
    }

    #[Assert\Callback]
    public function validateCategoryCodeSets(ExecutionContextInterface $context): void
    {
        $this->validateCategoryCodeSet($context, 'category', $this->category ?? null, self::CATEGORY_CODE_SET);
        $this->validateCategoryCodeSet($context, 'realEstateListing', $this->realEstateListing ?? null, self::REAL_ESTATE_LISTING_CODE_SET);
        $this->validateCategoryCodeSet($context, 'place', $this->place ?? null, self::PLACE_CODE_SET);

        foreach ($this->amenityFeature as $index => $amenityFeature) {
            $this->validateCategoryCodeSet($context, sprintf('amenityFeature[%d]', $index), $amenityFeature, self::AMENITY_FEATURE_CODE_SET);
        }

        foreach ($this->tags as $index => $tag) {
            $this->validateCategoryCodeSet($context, sprintf('tags[%d]', $index), $tag, self::TAG_CODE_SET);
        }
    }

    private function validateCategoryCodeSet(ExecutionContextInterface $context, string $path, ?CategoryCode $categoryCode, string $expectedCodeSet): void
    {
        if (!$categoryCode instanceof CategoryCode || $expectedCodeSet === $categoryCode->getInCodeSet()) {
            return;
        }

        $context
            ->buildViolation(sprintf('This CategoryCode must belong to "%s".', $expectedCodeSet))
            ->atPath($path)
            ->addViolation();
    }
}
