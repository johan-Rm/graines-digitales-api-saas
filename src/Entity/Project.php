<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\AutoSlug;
use App\Entity\Traits\UuidIdentity;
use App\State\ProjectByOwnerEmailProvider;
use App\State\ProjectPersistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['organization_id'], name: 'idx_project_organization')]
#[ORM\Index(columns: ['slug'], name: 'idx_project_slug')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('IS_AUTHENTICATED_FULLY')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Get(
            uriTemplate: '/projects/by-owner-email/{email}',
            uriVariables: ['email'],
            security: "is_granted('ROLE_ADMIN')",
            provider: ProjectByOwnerEmailProvider::class,
        ),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
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
    private array $enabledLocales = ['fr', 'en', 'es'];

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
        $this->slug = self::normalizeSlug($name);

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

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $mediaPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $mediaBaseUrl = null;

    public function getMediaPath(): ?string { return $this->mediaPath; }
    public function setMediaPath(?string $mediaPath): self { $this->mediaPath = $mediaPath; return $this; }

    public function getMediaBaseUrl(): ?string { return $this->mediaBaseUrl; }
    public function setMediaBaseUrl(?string $mediaBaseUrl): self { $this->mediaBaseUrl = $mediaBaseUrl; return $this; }

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $watermarkPath = null;

    public function getWatermarkPath(): ?string { return $this->watermarkPath; }
    public function setWatermarkPath(?string $watermarkPath): self { $this->watermarkPath = $watermarkPath; return $this; }
}
