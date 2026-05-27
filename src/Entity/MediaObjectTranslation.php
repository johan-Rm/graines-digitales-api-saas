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
#[ORM\Table(name: 'media_object_translation')]
#[ORM\UniqueConstraint(name: 'uniq_media_object_translation_locale', columns: ['media_object_id', 'locale'])]
#[ORM\Index(columns: ['project_id', 'locale'], name: 'idx_media_object_translation_project_locale')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/media-objects/{id}/translations', provider: ProjectScopedTranslationCollectionProvider::class),
        new Get(uriTemplate: '/projects/{projectId}/media-objects/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/media-objects/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class, processor: ProjectScopedTranslationPersistProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class MediaObjectTranslation implements ResourceTranslation
{
    use AuditTrail;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: MediaObject::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'media_object_id', nullable: false, onDelete: 'CASCADE')]
    private ?MediaObject $translatable = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $caption = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getTranslatable(): ?TranslatableResource { return $this->translatable; }
    public function setTranslatable(TranslatableResource $translatable): self { $this->translatable = $translatable; $this->project = $translatable->getProject(); return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $caption): self { $this->caption = $caption; return $this; }
}
