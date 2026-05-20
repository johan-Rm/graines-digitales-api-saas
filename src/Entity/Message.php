<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
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
#[ORM\Index(columns: ['project_id'], name: 'idx_message_project')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/messages', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/messages', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/messages/{id}', provider: ProjectScopedItemProvider::class),
        new Delete(uriTemplate: '/projects/{projectId}/messages/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class Message implements ProjectScopedResource
{
    use AuditTrail;
    use ProjectOwned;
    use UuidIdentity;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $firstName = '';

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 254)]
    #[Assert\Email]
    #[Groups(['read', 'write'])]
    private string $email = '';

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $phone = null;

    #[ORM\Column(type: 'text')]
    #[Assert\Length(min: 10, max: 5000)]
    #[Groups(['read', 'write'])]
    private string $message = '';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $propertyValue = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['write'])]
    private ?string $website = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): self { $this->lastName = $lastName; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }
    public function getPropertyValue(): ?array { return $this->propertyValue; }
    public function setPropertyValue(?array $propertyValue): self { $this->propertyValue = $propertyValue; return $this; }
    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): self { $this->website = $website; return $this; }
}
