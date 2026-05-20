<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\ProjectOwned;
use App\Entity\Traits\TranslatableResourceBehavior;
use App\Entity\Traits\UuidIdentity;
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedPersistProcessor;
use App\State\ProjectScopedRemoveProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_category_code_project')]
#[ORM\UniqueConstraint(name: 'uniq_category_code_project_code', columns: ['project_id', 'code'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/category-codes', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/category-codes', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class),
        new Delete(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class CategoryCode implements ProjectScopedResource, TranslatableResource
{
    use AuditTrail;
    use ProjectOwned;
    use TranslatableResourceBehavior;
    use UuidIdentity;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $code = '';

    /**
     * @var Collection<int, CategoryCodeTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: CategoryCodeTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['read', 'write'])]
    private Collection $translations;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->translations = new ArrayCollection();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    protected function createTranslation(): ResourceTranslation { return new CategoryCodeTranslation(); }
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
    #[Groups(['read', 'write'])]
    public function getLabel(): string { $translation = $this->getTranslation(); return $translation instanceof CategoryCodeTranslation ? $translation->getLabel() : ''; }
    public function setLabel(string $label): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setLabel($label); return $this; }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }
}
