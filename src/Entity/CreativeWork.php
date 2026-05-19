<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\ProjectOwned;
use App\Entity\Traits\UuidIdentity;
use App\Enum\ContentStatus;
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedPersistProcessor;
use App\State\ProjectScopedRemoveProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_creative_work_project')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/creative-works', provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/creative-works', processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/creative-works/{id}', provider: ProjectScopedItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/creative-works/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class),
        new Delete(uriTemplate: '/projects/{projectId}/creative-works/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class CreativeWork implements ProjectScopedResource
{
    use AuditTrail;
    use ProjectOwned;
    use UuidIdentity;

    #[ORM\Column(length: 180)]
    #[Groups(['read', 'write'])]
    private string $identifier = '';

    #[ORM\Column(length: 220, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $text = null;

    #[ORM\Column(enumType: ContentStatus::class)]
    #[Groups(['read', 'write'])]
    private ContentStatus $status = ContentStatus::Draft;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $schemaData = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getIdentifier(): string { return $this->identifier; }
    public function setIdentifier(string $identifier): self { $this->identifier = $identifier; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }
    public function getText(): ?string { return $this->text; }
    public function setText(?string $text): self { $this->text = $text; return $this; }
    public function getStatus(): ContentStatus { return $this->status; }
    public function setStatus(ContentStatus $status): self { $this->status = $status; return $this; }
    public function getSchemaData(): ?array { return $this->schemaData; }
    public function setSchemaData(?array $schemaData): self { $this->schemaData = $schemaData; return $this; }
}
