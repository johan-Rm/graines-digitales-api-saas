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
use App\Enum\ContentStatus;
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedPersistProcessor;
use App\State\ProjectScopedRemoveProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_creative_work_project')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/creative-works', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/creative-works', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/creative-works/{id}', provider: ProjectScopedItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/creative-works/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class),
        new Delete(uriTemplate: '/projects/{projectId}/creative-works/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class CreativeWork implements ProjectScopedResource, TranslatableResource
{
    use AuditTrail;
    use ProjectOwned;
    use TranslatableResourceBehavior;
    use UuidIdentity;

    #[ORM\Column(length: 180)]
    #[Groups(['read', 'write'])]
    private string $identifier = '';

    /**
     * @var Collection<int, CreativeWorkTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: CreativeWorkTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['read', 'write'])]
    private Collection $translations;

    #[ORM\Column(enumType: ContentStatus::class)]
    #[Groups(['read', 'write'])]
    private ContentStatus $status = ContentStatus::Draft;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->translations = new ArrayCollection();
    }

    public function getIdentifier(): string { return $this->identifier; }
    public function setIdentifier(string $identifier): self { $this->identifier = $identifier; return $this; }
    protected function createTranslation(): ResourceTranslation { return new CreativeWorkTranslation(); }
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
    public function getName(): ?string { $translation = $this->getTranslation(); return $translation instanceof CreativeWorkTranslation ? $translation->getName() : null; }
    public function setName(?string $name): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setName($name); return $this; }
    #[Groups(['read', 'write'])]
    public function getText(): ?string { $translation = $this->getTranslation(); return $translation instanceof CreativeWorkTranslation ? $translation->getText() : null; }
    public function setText(?string $text): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setText($text); return $this; }
    public function getStatus(): ContentStatus { return $this->status; }
    public function setStatus(ContentStatus $status): self { $this->status = $status; return $this; }
    #[Groups(['read', 'write'])]
    public function getSchemaData(): ?array { $translation = $this->getTranslation(); return $translation instanceof CreativeWorkTranslation ? $translation->getSchemaData() : null; }
    public function setSchemaData(?array $schemaData): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setSchemaData($schemaData); return $this; }
}
