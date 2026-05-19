<?php

namespace App\Entity;

interface ProjectScopedResource
{
    public function getProject(): ?Project;

    public function setProject(Project $project): self;

    public function getDeletedAt(): ?\DateTimeImmutable;

    public function archive(?User $actor = null): void;

    public function markCreatedBy(?User $actor): void;

    public function touch(?User $actor = null): void;
}
