<?php

namespace App\Entity;

use App\Entity\Traits\UuidIdentity;
use App\Enum\AccommodationQuality;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['accommodation_id', 'position'], name: 'idx_property_value_accommodation_position')]
class PropertyValue
{
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: Accommodation::class, inversedBy: 'qualities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Accommodation $accommodation = null;

    #[ORM\Column(length: 120, enumType: AccommodationQuality::class)]
    #[Assert\NotNull]
    #[Groups(['read', 'write'])]
    private AccommodationQuality $name = AccommodationQuality::Comfort;

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $value = 0;

    #[ORM\Column]
    private int $position = 0;

    public function __construct()
    {
        $this->initializeUuid();
    }

    public function getAccommodation(): ?Accommodation { return $this->accommodation; }
    public function setAccommodation(Accommodation $accommodation): self { $this->accommodation = $accommodation; return $this; }
    public function getName(): AccommodationQuality { return $this->name; }
    public function setName(AccommodationQuality $name): self { $this->name = $name; return $this; }
    public function getValue(): int { return $this->value; }
    public function setValue(int $value): self { $this->value = $value; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
}
