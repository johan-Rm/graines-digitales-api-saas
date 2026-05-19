<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\UuidIdentity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['slug'], name: 'idx_organization_slug')]
#[ApiResource(
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    security: "is_granted('ROLE_ADMIN')"
)]
class Organization
{
    use AuditTrail;
    use UuidIdentity;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $slug = '';

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
