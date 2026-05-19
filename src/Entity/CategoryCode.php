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
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_category_code_project')]
#[ORM\UniqueConstraint(name: 'uniq_category_code_project_code', columns: ['project_id', 'code'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/category-codes', provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/category-codes', processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class),
        new Delete(uriTemplate: '/projects/{projectId}/category-codes/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class CategoryCode implements ProjectScopedResource
{
    use AuditTrail;
    use ProjectOwned;
    use UuidIdentity;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $code = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $label = '';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

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
