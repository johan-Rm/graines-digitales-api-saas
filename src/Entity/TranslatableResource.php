<?php

namespace App\Entity;

use Doctrine\Common\Collections\Collection;

interface TranslatableResource extends ProjectScopedResource
{
    public function getTranslation(?string $locale = null): ?ResourceTranslation;

    public function getOrCreateTranslation(string $locale): ResourceTranslation;

    /**
     * @return Collection<int, ResourceTranslation>
     */
    public function getTranslations(): Collection;

    public function setCurrentLocale(string $locale): void;

    public function setResolvedLocale(string $requestedLocale, string $currentLocale, ?string $fallbackLocale = null): void;

    public function getCurrentLocale(): string;
}
