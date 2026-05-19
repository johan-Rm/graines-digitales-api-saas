<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\ResourceTranslationFields;
use App\Entity\Traits\UuidIdentity;
use App\State\ProjectScopedTranslationCollectionProvider;
use App\State\ProjectScopedTranslationItemProvider;
use App\State\ProjectScopedTranslationPersistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'creative_work_translation')]
#[ORM\UniqueConstraint(name: 'uniq_creative_work_translation_locale', columns: ['creative_work_id', 'locale'])]
#[ORM\Index(columns: ['project_id', 'locale'], name: 'idx_creative_work_translation_project_locale')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/creative-works/{id}/translations', provider: ProjectScopedTranslationCollectionProvider::class),
        new Get(uriTemplate: '/projects/{projectId}/creative-works/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/creative-works/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class, processor: ProjectScopedTranslationPersistProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class CreativeWorkTranslation implements ResourceTranslation
{
    use AuditTrail;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: CreativeWork::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'creative_work_id', nullable: false, onDelete: 'CASCADE')]
    private ?CreativeWork $translatable = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 220, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $text = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $schemaData = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getTranslatable(): ?TranslatableResource { return $this->translatable; }
    public function setTranslatable(TranslatableResource $translatable): self { $this->translatable = $translatable; $this->project = $translatable->getProject(); return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }
    public function getText(): ?string { return $this->text; }
    public function setText(?string $text): self { $this->text = $text; return $this; }
    public function getSchemaData(): ?array { return $this->schemaData; }
    public function setSchemaData(?array $schemaData): self { $this->schemaData = $schemaData; return $this; }
}
