<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\BulkMediaUploadController;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\ProjectOwned;
use App\Entity\Traits\TranslatableResourceBehavior;
use App\Entity\Traits\UuidIdentity;
use App\Enum\AiReviewStatus;
use App\Enum\MediaStatus;
use App\State\ProjectScopedCollectionProvider;
use App\State\ProjectScopedItemProvider;
use App\State\ProjectScopedPersistProcessor;
use App\State\ProjectScopedRemoveProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ORM\Entity]
#[ORM\Index(columns: ['project_id'], name: 'idx_media_object_project')]
#[ORM\Index(columns: ['checksum_sha256'], name: 'idx_media_object_checksum')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects/{projectId}/media-objects', uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])], provider: ProjectScopedCollectionProvider::class),
        new Post(
            uriTemplate: '/projects/{projectId}/media-objects/bulk',
            uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])],
            controller: BulkMediaUploadController::class,
            inputFormats: ['multipart' => ['multipart/form-data']],
            read: false,
            deserialize: false,
            openapi: new Model\Operation(
                summary: 'Upload multiple media objects for a project.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['files'],
                                'properties' => [
                                    'files' => [
                                        'type' => 'array',
                                        'maxItems' => 20,
                                        'items' => ['type' => 'string', 'format' => 'binary'],
                                    ],
                                    'caption' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ])
                )
            )
        ),
        new Post(
            uriTemplate: '/projects/{projectId}/media-objects',
            uriVariables: ['projectId' => new Link(fromClass: Project::class, identifiers: ['id'])],
            inputFormats: ['multipart' => ['multipart/form-data']],
            read: false,
            processor: ProjectScopedPersistProcessor::class,
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                    'caption' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ])
                )
            )
        ),
        new Get(uriTemplate: '/projects/{projectId}/media-objects/{id}', provider: ProjectScopedItemProvider::class),
        new Delete(uriTemplate: '/projects/{projectId}/media-objects/{id}', provider: ProjectScopedItemProvider::class, processor: ProjectScopedRemoveProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class MediaObject implements ProjectScopedResource, TranslatableResource
{
    use AuditTrail;
    use ProjectOwned;
    use TranslatableResourceBehavior;
    use UuidIdentity;

    /**
     * @var Collection<int, MediaObjectTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: MediaObjectTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['read', 'write'])]
    private Collection $translations;

    #[Vich\UploadableField(mapping: 'media_object', fileNameProperty: 'filePath', size: 'size', mimeType: 'mimeType', originalName: 'originalFilename')]
    #[Assert\NotNull(groups: ['media:create'])]
    #[Assert\File(maxSize: '12M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'])]
    #[Groups(['write'])]
    private ?File $file = null;

    #[ApiProperty(writable: false)]
    #[Groups(['read'])]
    public ?string $contentUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['read'])]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read'])]
    private ?int $size = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read'])]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['read'])]
    private ?string $checksumSha256 = null;

    #[ORM\Column(enumType: MediaStatus::class)]
    #[Groups(['read'])]
    private MediaStatus $status = MediaStatus::PendingWatermark;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $aiSuggestedFilename = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $aiSuggestedAlt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $aiSuggestedDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $finalFilename = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read'])]
    private ?string $mainEntity = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read'])]
    private ?string $finalAlt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read'])]
    private ?string $finalDescription = null;

    #[ORM\Column(enumType: AiReviewStatus::class)]
    #[Groups(['read'])]
    private AiReviewStatus $aiReviewStatus = AiReviewStatus::NotRequested;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->translations = new ArrayCollection();
    }

    protected function createTranslation(): ResourceTranslation { return new MediaObjectTranslation(); }
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
    public function getCaption(): ?string { $translation = $this->getTranslation(); return $translation instanceof MediaObjectTranslation ? $translation->getCaption() : null; }
    public function setCaption(?string $caption): self { $this->getOrCreateTranslation($this->getCurrentLocale())->setCaption($caption); return $this; }
    public function getFile(): ?File { return $this->file; }
    public function setFile(?File $file): self { $this->file = $file; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): self { $this->filePath = $filePath; return $this; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): self { $this->mimeType = $mimeType; return $this; }
    public function getSize(): ?int { return $this->size; }
    public function setSize(?int $size): self { $this->size = $size; return $this; }
    public function getOriginalFilename(): ?string { return $this->originalFilename; }
    public function setOriginalFilename(?string $originalFilename): self { $this->originalFilename = $originalFilename; return $this; }
    public function getChecksumSha256(): ?string { return $this->checksumSha256; }
    public function setChecksumSha256(?string $checksumSha256): self { $this->checksumSha256 = $checksumSha256; return $this; }
    public function getStatus(): MediaStatus { return $this->status; }
    public function setStatus(MediaStatus $status): self { $this->status = $status; return $this; }
    public function getAiSuggestedFilename(): ?string { return $this->aiSuggestedFilename; }
    public function setAiSuggestedFilename(?string $aiSuggestedFilename): self { $this->aiSuggestedFilename = $aiSuggestedFilename; return $this; }
    public function getAiSuggestedAlt(): ?string { return $this->aiSuggestedAlt; }
    public function setAiSuggestedAlt(?string $aiSuggestedAlt): self { $this->aiSuggestedAlt = $aiSuggestedAlt; return $this; }
    public function getAiSuggestedDescription(): ?string { return $this->aiSuggestedDescription; }
    public function setAiSuggestedDescription(?string $aiSuggestedDescription): self { $this->aiSuggestedDescription = $aiSuggestedDescription; return $this; }
    public function getFinalFilename(): ?string { return $this->finalFilename; }
    public function setFinalFilename(?string $finalFilename): self { $this->finalFilename = $finalFilename; return $this; }
    public function getMainEntity(): ?string { return $this->mainEntity; }
    public function setMainEntity(?string $mainEntity): self { $this->mainEntity = $mainEntity; return $this; }
    public function getFinalAlt(): ?string { return $this->finalAlt; }
    public function setFinalAlt(?string $finalAlt): self { $this->finalAlt = $finalAlt; return $this; }
    public function getFinalDescription(): ?string { return $this->finalDescription; }
    public function setFinalDescription(?string $finalDescription): self { $this->finalDescription = $finalDescription; return $this; }
    public function getAiReviewStatus(): AiReviewStatus { return $this->aiReviewStatus; }
    public function setAiReviewStatus(AiReviewStatus $aiReviewStatus): self { $this->aiReviewStatus = $aiReviewStatus; return $this; }
}
