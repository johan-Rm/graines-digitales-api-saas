<?php

namespace App\Entity;

use App\Entity\Traits\UuidIdentity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Index(columns: ['web_page_id', 'position'], name: 'idx_web_page_media_position')]
class WebPageMedia
{
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: WebPage::class, inversedBy: 'associatedMedia')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WebPage $webPage = null;

    #[ORM\ManyToOne(targetEntity: MediaObject::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read', 'write'])]
    private ?MediaObject $mediaObject = null;

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $position = 0;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $caption = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $keywords = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?bool $representativeOfPage = null;

    public function __construct()
    {
        $this->initializeUuid();
    }

    public function getWebPage(): ?WebPage { return $this->webPage; }
    public function setWebPage(WebPage $webPage): self { $this->webPage = $webPage; return $this; }
    public function getMediaObject(): ?MediaObject { return $this->mediaObject; }
    public function setMediaObject(MediaObject $mediaObject): self { $this->mediaObject = $mediaObject; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $caption): self { $this->caption = $caption; return $this; }
    /**
     * @return list<string>
     */
    public function getKeywords(): array { return $this->keywords; }
    /**
     * @param list<string> $keywords
     */
    public function setKeywords(array $keywords): self { $this->keywords = $keywords; return $this; }
    public function isRepresentativeOfPage(): ?bool { return $this->representativeOfPage; }
    public function setRepresentativeOfPage(?bool $representativeOfPage): self { $this->representativeOfPage = $representativeOfPage; return $this; }
}
