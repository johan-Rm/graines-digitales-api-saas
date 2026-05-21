<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\AutoSlug;
use App\Entity\Traits\UuidIdentity;
use App\State\ProjectPersistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['organization_id'], name: 'idx_project_organization')]
#[ORM\Index(columns: ['slug'], name: 'idx_project_slug')]
#[ApiResource(
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    security: "is_granted('ROLE_ADMIN')",
    processor: ProjectPersistProcessor::class
)]
class Project
{
    use AuditTrail;
    use AutoSlug;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?Organization $organization = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $slug = '';

    #[ORM\Column(length: 8)]
    #[Assert\Choice(choices: ['fr', 'en', 'es', 'ar'])]
    #[Groups(['read', 'write'])]
    private string $sourceLocale = 'fr';

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $enabledLocales = ['fr'];

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        if ('' === $this->slug) {
            $this->slug = self::normalizeSlug($name);
        }

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = self::normalizeSlug($slug);

        return $this;
    }

    public function getSourceLocale(): string
    {
        return $this->sourceLocale;
    }

    public function setSourceLocale(string $sourceLocale): self
    {
        $this->sourceLocale = $sourceLocale;

        if (!in_array($sourceLocale, $this->enabledLocales, true)) {
            $this->enabledLocales[] = $sourceLocale;
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    /**
     * @param list<string> $enabledLocales
     */
    public function setEnabledLocales(array $enabledLocales): self
    {
        $locales = array_values(array_unique($enabledLocales));
        $this->enabledLocales = [] === $locales ? [$this->sourceLocale] : $locales;

        if (!in_array($this->sourceLocale, $this->enabledLocales, true)) {
            $this->enabledLocales[] = $this->sourceLocale;
        }

        return $this;
    }
}
