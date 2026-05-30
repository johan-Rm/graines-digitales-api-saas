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
use App\Entity\Traits\AutoSlug;
use App\Entity\Traits\ProjectOwned;
use App\Entity\Traits\TranslatableResourceBehavior;
use App\Entity\Traits\UuidIdentity;
use App\State\CategoryCodePersistProcessor;
use App\State\CategoryCodeRemoveProcessor;
use App\State\ProjectScopedBulkTranslationCreateProcessor;
use App\State\ProjectScopedBulkTranslationProcessor;
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedPersistProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_category_code_project')]
#[ORM\UniqueConstraint(name: 'uniq_category_code_project_set_code_value', columns: ['project_id', 'in_code_set', 'code_value'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/category-codes', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/category-codes', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], read: false, processor: CategoryCodePersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class),
        new Get(uriTemplate: '/projects/{projectId}/category-codes/{id}/translations', name: 'get_category_code_translations', provider: ProjectScopedItemProvider::class, normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Put(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class),
        new Put(uriTemplate: '/projects/{projectId}/category-codes/{id}/translations', name: 'put_category_code_translations', provider: ProjectScopedItemProvider::class, processor: ProjectScopedBulkTranslationProcessor::class, denormalizationContext: ['groups' => ['write:translations']], normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Post(uriTemplate: '/projects/{projectId}/category-codes/translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], name: 'post_category_code_translations', read: false, deserialize: false, status: 201, processor: ProjectScopedBulkTranslationCreateProcessor::class, normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Delete(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class, processor: CategoryCodeRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class CategoryCode implements ProjectScopedResource, TranslatableResource
{
    use AuditTrail;
    use AutoSlug;
    use ProjectOwned;
    use TranslatableResourceBehavior;
    use UuidIdentity;

    #[ORM\Column(length: 120)]
    #[Assert\Length(max: 120)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'This value must be kebab-case.')]
    private string $codeValue = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['read', 'write'])]
    private string $inCodeSet = '';

    /**
     * @var Collection<int, CategoryCodeTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: CategoryCodeTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['read', 'write:translations'])]
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

    #[Groups(['read'])]
    public function getCodeValue(): string
    {
        return $this->codeValue;
    }

    public function setCodeValue(string $codeValue): self
    {
        $this->codeValue = $codeValue;

        return $this;
    }

    public function generateCodeFromLabel(string $sourceLocale): void
    {
        if ('' !== $this->codeValue) {
            return;
        }

        $translation = $this->getTranslation($sourceLocale);
        if ($translation instanceof CategoryCodeTranslation && '' !== $translation->getLabel()) {
            $this->codeValue = self::buildSlug($translation->getLabel());
        }
    }

    public function getInCodeSet(): string
    {
        return $this->inCodeSet;
    }

    public function setInCodeSet(string $inCodeSet): self
    {
        $this->inCodeSet = $inCodeSet;

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
    public function setLabel(string $label): self { $translation = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($translation instanceof CategoryCodeTranslation) { $translation->setLabel($label); } return $this; }

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
