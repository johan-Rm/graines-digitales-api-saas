<?php

namespace App\Entity;

interface ProjectScopedResource
{
    public function getProject(): ?Project;

    public function setProject(Project $project): self;

    public function markCreatedBy(?User $actor): void;

    public function touch(?User $actor = null): void;
}
