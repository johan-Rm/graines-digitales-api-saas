<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\ProjectOwned;
use App\Entity\Traits\TranslatableResourceBehavior;
use App\Entity\Traits\UuidIdentity;
use App\Enum\ContentStatus;
use App\State\ProjectScopedBulkTranslationCreateProcessor;
use App\State\ProjectScopedBulkTranslationProcessor;
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
        new GetCollection(uriTemplate: '/projects/{projectId}/web-pages', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedCollectionProvider::class),
        new Post(uriTemplate: '/projects/{projectId}/web-pages', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], read: false, processor: ProjectScopedPersistProcessor::class),
        new Get(uriTemplate: '/projects/{projectId}/web-pages/{id}', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: WebPage::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class),
        new Get(uriTemplate: '/projects/{projectId}/web-pages/{id}/translations', name: 'get_web_page_translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: WebPage::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class, normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Put(uriTemplate: '/projects/{projectId}/web-pages/{id}', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: WebPage::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class, processor: ProjectScopedPersistProcessor::class, denormalizationContext: ['groups' => ['write'], 'api_assign_object_to_populate' => true]),
        new Put(uriTemplate: '/projects/{projectId}/web-pages/{id}/translations', name: 'put_web_page_translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: WebPage::class, identifiers: ['id'])], deserialize: false, provider: ProjectScopedItemProvider::class, processor: ProjectScopedBulkTranslationProcessor::class, normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Post(uriTemplate: '/projects/{projectId}/web-pages/translations', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], name: 'post_web_page_translations', read: false, deserialize: false, status: 201, processor: ProjectScopedBulkTranslationCreateProcessor::class, normalizationContext: ['groups' => ['read', 'read:with-translations']]),
        new Delete(uriTemplate: '/projects/{projectId}/web-pages/{id}', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'], toProperty: 'project'), 'id' => new Link(fromClass: WebPage::class, identifiers: ['id'])], provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
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
    #[Groups(['read', 'write:translations'])]
    private Collection $translations;

    /**
     * @var Collection<int, WebPageMedia>
     */
    #[ORM\OneToMany(mappedBy: 'webPage', targetEntity: WebPageMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['read', 'write'])]
    private Collection $associatedMedia;

    #[ORM\Column(enumType: ContentStatus::class)]
    #[Groups(['read', 'write'])]
    private ContentStatus $status = ContentStatus::Draft;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->translations = new ArrayCollection();
        $this->associatedMedia = new ArrayCollection();
    }

    protected function createTranslation(): ResourceTranslation { return new WebPageTranslation(); }
    protected function addTranslation(ResourceTranslation $translation): self { if (!$this->translations->contains($translation)) { $this->translations->add($translation); } return $this; }
    public function getTranslations(): Collection { return $this->translations; }
    public function getAssociatedMedia(): Collection { return $this->associatedMedia; }
    public function addAssociatedMedia(WebPageMedia $media): self { if (!$this->associatedMedia->contains($media)) { $this->associatedMedia->add($media); $media->setWebPage($this); } return $this; }
    public function addAssociatedMedium(WebPageMedia $media): self { return $this->addAssociatedMedia($media); }
    public function removeAssociatedMedia(WebPageMedia $media): self { $this->associatedMedia->removeElement($media); return $this; }
    public function removeAssociatedMedium(WebPageMedia $media): self { return $this->removeAssociatedMedia($media); }
    public function clearAssociatedMedia(): self { $this->associatedMedia->clear(); return $this; }
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
    public function setSlug(string $slug): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof WebPageTranslation) { $t->setSlug($slug); } return $this; }
    #[Groups(['read', 'write'])]
    public function getInLanguage(): string { return $this->getLocale(); }
    public function setInLanguage(string $inLanguage): self { $this->setCurrentLocale($inLanguage); return $this; }
    #[Groups(['read', 'write'])]
    public function getMetaTitle(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getMetaTitle() : null; }
    public function setMetaTitle(?string $metaTitle): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof WebPageTranslation) { $t->setMetaTitle($metaTitle); } return $this; }
    #[Groups(['read', 'write'])]
    public function getMetaDescription(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getMetaDescription() : null; }
    public function setMetaDescription(?string $metaDescription): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof WebPageTranslation) { $t->setMetaDescription($metaDescription); } return $this; }
    #[Groups(['read', 'write'])]
    public function getHeadline(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getHeadline() : null; }
    public function setHeadline(?string $headline): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof WebPageTranslation) { $t->setHeadline($headline); } return $this; }
    #[Groups(['read', 'write'])]
    public function getBody(): ?string { $translation = $this->getTranslation(); return $translation instanceof WebPageTranslation ? $translation->getBody() : null; }
    public function setBody(?string $body): self { $t = $this->getOrCreateTranslation($this->getCurrentLocale()); if ($t instanceof WebPageTranslation) { $t->setBody($body); } return $this; }
    public function getStatus(): ContentStatus { return $this->status; }
    public function setStatus(ContentStatus $status): self { $this->status = $status; return $this; }
}
