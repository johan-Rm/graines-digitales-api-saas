<?php

namespace App\Entity;

use App\Entity\Traits\HiddenAuditTrail;
use App\Entity\Traits\TranslatableResourceBehavior;
use App\Entity\Traits\UuidIdentity;
use App\Enum\AccommodationComponentType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
#[ORM\Table(name: 'accommodation_component')]
#[ORM\Index(columns: ['accommodation_id', 'position'], name: 'idx_accommodation_component_position')]
#[ORM\Index(columns: ['project_id'], name: 'idx_accommodation_component_project')]
class AccommodationComponent implements TranslatableResource
{
    use HiddenAuditTrail;
    use TranslatableResourceBehavior;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: Accommodation::class, inversedBy: 'hasPart')]
    #[ORM\JoinColumn(name: 'accommodation_id', nullable: false, onDelete: 'CASCADE')]
    private ?Accommodation $accommodation = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(enumType: AccommodationComponentType::class)]
    #[Groups(['read'])]
    private AccommodationComponentType $additionalType = AccommodationComponentType::Full;

    #[ORM\Column]
    #[Groups(['read'])]
    private int $position = 0;

    /**
     * Ordered list of MediaObject UUIDs referenced by this component.
     * Denormalized reference: no foreign-key integrity is enforced.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['read'])]
    private array $associatedMedia = [];

    /**
     * Free-form display options for the component. The shape is intentionally open:
     * the API stores and returns whatever object the client provides.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['read'])]
    private array $meta = [];

    /**
     * @var Collection<int, AccommodationComponentTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: AccommodationComponentTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->translations = new ArrayCollection();
    }

    public function getAccommodation(): ?Accommodation
    {
        return $this->accommodation;
    }

    public function setAccommodation(?Accommodation $accommodation): self
    {
        $this->accommodation = $accommodation;
        if ($accommodation instanceof Accommodation) {
            $project = $accommodation->getProject();
            if ($project instanceof Project) {
                $this->setProject($project);
            }
        }

        return $this;
    }

    #[Ignore]
    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getAdditionalType(): AccommodationComponentType
    {
        return $this->additionalType;
    }

    public function setAdditionalType(AccommodationComponentType $additionalType): self
    {
        $this->additionalType = $additionalType;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAssociatedMedia(): array
    {
        return $this->associatedMedia;
    }

    /**
     * @param list<string> $associatedMedia
     */
    public function setAssociatedMedia(array $associatedMedia): self
    {
        $this->associatedMedia = array_values(array_filter($associatedMedia, 'is_string'));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function setMeta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    #[Groups(['read'])]
    public function getName(): ?string
    {
        $translation = $this->getTranslation();

        return $translation instanceof AccommodationComponentTranslation ? $translation->getName() : null;
    }

    public function setName(?string $name): self
    {
        $translation = $this->getOrCreateTranslation($this->getCurrentLocale());
        if ($translation instanceof AccommodationComponentTranslation) {
            $translation->setName($name);
        }

        return $this;
    }

    #[Groups(['read'])]
    public function getHeadline(): ?string
    {
        $translation = $this->getTranslation();

        return $translation instanceof AccommodationComponentTranslation ? $translation->getHeadline() : null;
    }

    public function setHeadline(?string $headline): self
    {
        $translation = $this->getOrCreateTranslation($this->getCurrentLocale());
        if ($translation instanceof AccommodationComponentTranslation) {
            $translation->setHeadline($headline);
        }

        return $this;
    }

    #[Groups(['read'])]
    public function getText(): ?string
    {
        $translation = $this->getTranslation();

        return $translation instanceof AccommodationComponentTranslation ? $translation->getText() : null;
    }

    public function setText(?string $text): self
    {
        $translation = $this->getOrCreateTranslation($this->getCurrentLocale());
        if ($translation instanceof AccommodationComponentTranslation) {
            $translation->setText($text);
        }

        return $this;
    }

    protected function createTranslation(): ResourceTranslation
    {
        return new AccommodationComponentTranslation();
    }

    protected function addTranslation(ResourceTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }

        return $this;
    }

    /**
     * @return Collection<int, ResourceTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
