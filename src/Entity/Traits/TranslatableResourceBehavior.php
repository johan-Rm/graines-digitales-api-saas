<?php

namespace App\Entity\Traits;

use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;
use Doctrine\Common\Collections\Collection;

trait TranslatableResourceBehavior
{
    private string $currentLocale = 'fr';
    private string $requestedLocale = 'fr';
    private ?string $fallbackLocale = null;
    private bool $fallback = false;

    public function getTranslation(?string $locale = null): ?ResourceTranslation
    {
        $locale ??= $this->currentLocale;

        foreach ($this->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function getOrCreateTranslation(string $locale): ResourceTranslation
    {
        $translation = $this->getTranslation($locale);
        if ($translation instanceof ResourceTranslation) {
            return $translation;
        }

        $translation = $this->createTranslation();
        $translation->setLocale($locale);
        $translation->setTranslatable($this);
        $this->addTranslation($translation);

        return $translation;
    }

    public function setCurrentLocale(string $locale): void
    {
        $this->currentLocale = $locale;
    }

    public function setResolvedLocale(string $requestedLocale, string $currentLocale, ?string $fallbackLocale = null): void
    {
        $this->requestedLocale = $requestedLocale;
        $this->currentLocale = $currentLocale;
        $this->fallbackLocale = $fallbackLocale;
        $this->fallback = null !== $fallbackLocale && $requestedLocale !== $currentLocale;
    }

    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    public function getRequestedLocale(): string
    {
        return $this->requestedLocale;
    }

    public function getLocale(): string
    {
        return $this->currentLocale;
    }

    public function getFallbackLocale(): ?string
    {
        return $this->fallbackLocale;
    }

    public function isFallback(): bool
    {
        return $this->fallback;
    }

    abstract protected function createTranslation(): ResourceTranslation;

    abstract protected function addTranslation(ResourceTranslation $translation): self;

    public function removeTranslation(ResourceTranslation $translation): self
    {
        $this->getTranslations()->removeElement($translation);

        return $this;
    }

    /**
     * @return Collection<int, ResourceTranslation>
     */
    abstract public function getTranslations(): Collection;
}
