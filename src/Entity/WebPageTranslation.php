<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\AutoSlug;
use App\Entity\Traits\ResourceTranslationFields;
use App\Entity\Traits\UuidIdentity;
use App\State\ProjectScopedTranslationCollectionProvider;
use App\Translation\AutoTranslate;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'web_page_translation')]
#[ORM\UniqueConstraint(name: 'uniq_web_page_translation_locale', columns: ['web_page_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_web_page_translation_project_slug_locale', columns: ['project_id', 'slug', 'locale'])]
#[ORM\Index(columns: ['project_id', 'locale'], name: 'idx_web_page_translation_project_locale')]
#[ORM\Index(columns: ['slug'], name: 'idx_web_page_translation_slug')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/web-pages/{id}/translations', provider: ProjectScopedTranslationCollectionProvider::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class WebPageTranslation implements ResourceTranslation
{
    use AuditTrail;
    use AutoSlug;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: WebPage::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'web_page_id', nullable: false, onDelete: 'CASCADE')]
    private ?WebPage $translatable = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Groups(['read'])]
    private string $slug = '';

    #[ORM\Column(length: 220, nullable: true)]
    #[AutoTranslate]
    #[Groups(['read', 'write'])]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 320, nullable: true)]
    #[AutoTranslate]
    #[Groups(['read', 'write'])]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 220, nullable: true)]
    #[AutoTranslate]
    #[Groups(['read', 'write'])]
    private ?string $headline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[AutoTranslate]
    #[Groups(['read', 'write'])]
    private ?string $body = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getTranslatable(): ?TranslatableResource { return $this->translatable; }
    public function setTranslatable(TranslatableResource $translatable): self { $this->translatable = $translatable; $this->project = $translatable->getProject(); return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = self::normalizeSlug($slug); return $this; }
    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function setMetaTitle(?string $metaTitle): self { $this->metaTitle = $metaTitle; return $this; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): self { $this->metaDescription = $metaDescription; return $this; }
    public function getHeadline(): ?string { return $this->headline; }
    public function setHeadline(?string $headline): self { $this->headline = $headline; if ('' === $this->slug) { $this->slug = self::normalizeSlug($headline); } return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): self { $this->body = $body; return $this; }
}
