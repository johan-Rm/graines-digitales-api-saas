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
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'menu_item_translation')]
#[ORM\UniqueConstraint(name: 'uniq_menu_item_translation_locale', columns: ['menu_item_id', 'locale'])]
#[ORM\Index(columns: ['project_id', 'locale'], name: 'idx_menu_item_translation_project_locale')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/menu-items/{id}/translations', provider: ProjectScopedTranslationCollectionProvider::class),
        new Get(uriTemplate: '/projects/{projectId}/menu-items/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class),
        new Put(uriTemplate: '/projects/{projectId}/menu-items/{id}/translations/{locale}', provider: ProjectScopedTranslationItemProvider::class, processor: ProjectScopedTranslationPersistProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class MenuItemTranslation implements ResourceTranslation
{
    use AuditTrail;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: MenuItem::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'menu_item_id', nullable: false, onDelete: 'CASCADE')]
    private ?MenuItem $translatable = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $label = '';

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getTranslatable(): ?TranslatableResource { return $this->translatable; }
    public function setTranslatable(TranslatableResource $translatable): self { $this->translatable = $translatable; $this->project = $translatable->getProject(); return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
}
