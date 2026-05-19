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
use App\Entity\Traits\TranslatableResourceBehavior;
use App\Entity\Traits\UuidIdentity;
use App\Enum\ContentStatus;
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedPersistProcessor;
use App\State\ProjectScopedRemoveProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_web_page_project')]
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
class WebPage implements ProjectScopedResource, TranslatableResource
{
    use AuditTrail;
    use ProjectOwned;
    use TranslatableResourceBehavior;
    use UuidIdentity;

    /**
     * @var Collection<int, WebPageTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: WebPageTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['read', 'write'])]
    private Collection $translations;

    #[ORM\Column(enumType: ContentStatus::class)]
    #[Groups(['read', 'write'])]
    private ContentStatus $status = ContentStatus::Draft;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->translations = new ArrayCollection();
    }

    protected function createTranslation(): ResourceTranslation { return new WebPageTranslation(); }
    protected function addTranslation(ResourceTranslation $translation): self { if (!$this->translations->contains($translation)) { $this->translations->add($translation); } return $this; }
    public function getTranslations(): Collection { return $this->translations; }
    #[Groups(['read'])]
    public function getRequestedLocale(): string { return $this->requestedLocale; }
    #[Groups(['read'])]
    public function getLocale(): string { return $this->currentLocale; }
    #[Groups(['read'])]
    public function getFallbackLocale(): ?string { return $this->fallbackLocale; }
    #[Groups(['read'])]
    public function isFallback(): bool { return $this->fallback; }
    #[Groups(['read', 'write'])]
    public function getSlug(): string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getSlug() : ''; }
    public function setSlug(string $slug): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setSlug($slug); return $this; }
    #[Groups(['read', 'write'])]
    public function getInLanguage(): string { return $this->getLocale(); }
    public function setInLanguage(string $inLanguage): self { $this->setCurrentLocale($inLanguage); return $this; }
    #[Groups(['read', 'write'])]
    public function getMetaTitle(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getMetaTitle() : null; }
    public function setMetaTitle(?string $metaTitle): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setMetaTitle($metaTitle); return $this; }
    #[Groups(['read', 'write'])]
    public function getMetaDescription(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getMetaDescription() : null; }
    public function setMetaDescription(?string $metaDescription): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setMetaDescription($metaDescription); return $this; }
    #[Groups(['read', 'write'])]
    public function getHeadline(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getHeadline() : null; }
    public function setHeadline(?string $headline): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setHeadline($headline); return $this; }
    #[Groups(['read', 'write'])]
    public function getBody(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getBody() : null; }
    public function setBody(?string $body): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setBody($body); return $this; }
    public function getStatus(): ContentStatus { return $this->status; }
    public function setStatus(ContentStatus $status): self { $this->status = $status; return $this; }
    #[Groups(['read', 'write'])]
    public function getSchemaData(): ?array { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getSchemaData() : null; }
    public function setSchemaData(?array $schemaData): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setSchemaData($schemaData); return $this; }
}
