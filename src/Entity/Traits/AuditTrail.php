<?php

namespace App\Entity\Traits;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait AuditTrail
{
    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['read'])]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['read'])]
    private ?User $updatedBy = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $deletedAt = null;

    public function initializeAuditTrail(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(?User $actor = null): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedBy = $actor;
    }

    public function markCreatedBy(?User $actor): void
    {
        $this->createdBy = $actor;
        $this->updatedBy = $actor;
    }

    public function archive(?User $actor = null): void
    {
        $this->deletedAt = new \DateTimeImmutable();
        $this->touch($actor);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
}
