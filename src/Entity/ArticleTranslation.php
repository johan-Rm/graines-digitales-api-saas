<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\AutoSlug;
use App\Entity\Traits\ResourceTranslationFields;
use App\Entity\Traits\UuidIdentity;
use App\State\ProjectScopedTranslationCollectionProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'article_translation')]
#[ORM\UniqueConstraint(name: 'uniq_article_translation_locale', columns: ['article_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_article_translation_project_slug_locale', columns: ['project_id', 'slug', 'locale'])]
#[ORM\Index(columns: ['project_id', 'locale'], name: 'idx_article_translation_project_locale')]
#[ORM\Index(columns: ['slug'], name: 'idx_article_translation_slug')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/articles/{id}/translations', provider: ProjectScopedTranslationCollectionProvider::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class ArticleTranslation implements ResourceTranslation
{
    use AuditTrail;
    use AutoSlug;
    use ResourceTranslationFields;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'article_id', nullable: false, onDelete: 'CASCADE')]
    private ?Article $translatable = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Groups(['read'])]
    private string $slug = '';

    #[ORM\Column(length: 220)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $headline = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $body = null;

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
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = self::normalizeSlug($slug); return $this; }
    public function getHeadline(): string { return $this->headline; }
    public function setHeadline(string $headline): self { $this->headline = $headline; if ('' === $this->slug) { $this->slug = self::normalizeSlug($headline); } return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): self { $this->body = $body; return $this; }
    public function getSchemaData(): ?array { return $this->schemaData; }
    public function setSchemaData(?array $schemaData): self { $this->schemaData = $schemaData; return $this; }
}
