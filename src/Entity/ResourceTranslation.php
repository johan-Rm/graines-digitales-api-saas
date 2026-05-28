<?php

namespace App\Entity;

interface ResourceTranslation
{
    public function getLocale(): string;

    public function setLocale(string $locale): self;

    public function getTranslatable(): ?TranslatableResource;

    public function setTranslatable(TranslatableResource $translatable): self;

    public function touch(?User $actor = null): void;
}
