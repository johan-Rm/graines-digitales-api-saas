<?php

namespace App\Entity;

use App\Entity\Traits\HiddenAuditTrail;
use App\Entity\Traits\ResourceTranslationFields;
use App\Entity\Traits\UuidIdentity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'accommodation_component_translation')]
#[ORM\UniqueConstraint(name: 'uniq_accommodation_component_translation_locale', columns: ['component_id', 'locale'])]
#[ORM\Index(columns: ['component_id', 'locale'], name: 'idx_accommodation_component_translation_locale')]
class AccommodationComponentTranslation implements ResourceTranslation
{
    use HiddenAuditTrail;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: AccommodationComponent::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'component_id', nullable: false, onDelete: 'CASCADE')]
    private ?AccommodationComponent $translatable = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $name = null;

    #[ORM\Column(length: 260, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $headline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $text = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getTranslatable(): ?TranslatableResource
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableResource $translatable): self
    {
        $this->translatable = $translatable instanceof AccommodationComponent ? $translatable : null;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): self
    {
        $this->headline = $headline;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
