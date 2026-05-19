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
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedPersistProcessor;
use App\State\ProjectScopedRemoveProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_menu_item_project')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/menu-items', provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/menu-items', processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/menu-items/{id}', provider: ProjectScopedItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/menu-items/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class),
        new Delete(uriTemplate: '/projects/{projectId}/menu-items/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class MenuItem implements ProjectScopedResource
{
    use AuditTrail;
    use ProjectOwned;
    use UuidIdentity;

    #[ORM\Column(length: 160)]
    #[Groups(['read', 'write'])]
    private string $label = '';

    #[ORM\Column(length: 220)]
    #[Groups(['read', 'write'])]
    private string $url = '';

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $position = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): self { $this->url = $url; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): self { $this->metadata = $metadata; return $this; }
}
