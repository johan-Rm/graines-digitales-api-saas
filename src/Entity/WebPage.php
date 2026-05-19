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
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_web_page_project')]
#[ORM\Index(columns: ['slug'], name: 'idx_web_page_slug')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/web-pages', provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/web-pages', processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/web-pages/{id}', provider: ProjectScopedItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/web-pages/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class),
        new Delete(uriTemplate: '/projects/{projectId}/web-pages/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class WebPage implements ProjectScopedResource
{
    use AuditTrail;
    use ProjectOwned;
    use UuidIdentity;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $slug = '';

    #[ORM\Column(length: 8)]
    #[Groups(['read', 'write'])]
    private string $inLanguage = 'fr';

    #[ORM\Column(length: 220, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 320, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 220, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $headline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $body = null;

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

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getInLanguage(): string { return $this->inLanguage; }
    public function setInLanguage(string $inLanguage): self { $this->inLanguage = $inLanguage; return $this; }
    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function setMetaTitle(?string $metaTitle): self { $this->metaTitle = $metaTitle; return $this; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): self { $this->metaDescription = $metaDescription; return $this; }
    public function getHeadline(): ?string { return $this->headline; }
    public function setHeadline(?string $headline): self { $this->headline = $headline; return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): self { $this->body = $body; return $this; }
    public function getStatus(): ContentStatus { return $this->status; }
    public function setStatus(ContentStatus $status): self { $this->status = $status; return $this; }
    public function getSchemaData(): ?array { return $this->schemaData; }
    public function setSchemaData(?array $schemaData): self { $this->schemaData = $schemaData; return $this; }
}
