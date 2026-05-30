<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\HiddenAuditTrail;
use App\Entity\Traits\ResourceTranslationFields;
use App\Entity\Traits\UuidIdentity;
use App\Translation\AutoTranslate;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'category_code_translation')]
#[ORM\UniqueConstraint(name: 'uniq_category_code_translation_locale', columns: ['category_code_id', 'locale'])]
#[ORM\Index(columns: ['project_id', 'locale'], name: 'idx_category_code_translation_project_locale')]
#[ApiResource(
    operations: [],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class CategoryCodeTranslation implements ResourceTranslation
{
    use HiddenAuditTrail;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: CategoryCode::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'category_code_id', nullable: false, onDelete: 'CASCADE')]
    private ?CategoryCode $translatable = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[AutoTranslate]
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
