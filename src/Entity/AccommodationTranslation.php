<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\AutoSlug;
use App\Entity\Traits\ResourceTranslationFields;
use App\Entity\Traits\UuidIdentity;
use App\State\ProjectScopedAccommodationTranslationCollectionProvider;
use App\State\ProjectScopedTranslationCollectionProvider;
use App\State\ProjectScopedTranslationItemProvider;
use App\State\ProjectScopedTranslationPersistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'accommodation_translation')]
#[ORM\UniqueConstraint(name: 'uniq_accommodation_translation_locale', columns: ['accommodation_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_accommodation_translation_project_slug_locale', columns: ['project_id', 'slug', 'locale'])]
#[ORM\Index(columns: ['project_id', 'locale'], name: 'idx_accommodation_translation_project_locale')]
#[ORM\Index(columns: ['slug'], name: 'idx_accommodation_translation_slug')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/accommodation-translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedAccommodationTranslationCollectionProvider::class),
        new GetCollection(uriTemplate: '/projects/{projectId}/accommodations/{id}/translations', provider: ProjectScopedTranslationCollectionProvider::class),
        new Get(uriTemplate: '/projects/{projectId}/accommodations/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/accommodations/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class, processor: ProjectScopedTranslationPersistProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class AccommodationTranslation implements ResourceTranslation
{
    use AuditTrail;
    use AutoSlug;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: Accommodation::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'accommodation_id', nullable: false, onDelete: 'CASCADE')]
    private ?Accommodation $translatable = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $slug = '';

    #[ORM\Column(length: 220, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $name = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $label = null;

    #[ORM\Column(length: 260, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $highlight = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $body = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $review = null;

    #[ORM\Column(length: 220, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 320, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $locationDescription = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getTranslatable(): ?TranslatableResource { return $this->translatable; }
    public function setTranslatable(TranslatableResource $translatable): self { $this->translatable = $translatable; $this->project = $translatable->getProject(); return $this; }
    #[Groups(['read'])]
    public function getAccommodation(): ?string { return $this->translatable instanceof Accommodation ? (string) $this->translatable->getId() : null; }
    public function getProject(): ?Project { return $this->project; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = self::normalizeSlug($slug); return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; if ('' === $this->slug) { $this->slug = self::normalizeSlug($name); } return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): self { $this->label = $label; return $this; }
    public function getHighlight(): ?string { return $this->highlight; }
    public function setHighlight(?string $highlight): self { $this->highlight = $highlight; return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): self { $this->body = $body; return $this; }
    public function getReview(): ?string { return $this->review; }
    public function setReview(?string $review): self { $this->review = $review; return $this; }
    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function setMetaTitle(?string $metaTitle): self { $this->metaTitle = $metaTitle; return $this; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): self { $this->metaDescription = $metaDescription; return $this; }
    public function getLocationDescription(): ?string { return $this->locationDescription; }
    public function setLocationDescription(?string $locationDescription): self { $this->locationDescription = $locationDescription; return $this; }
}
