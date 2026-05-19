<?php

namespace App\Entity\Traits;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

trait ResourceTranslationFields
{
    #[ORM\Column(length: 8)]
    #[Assert\Choice(choices: ['fr', 'en', 'es', 'ar'])]
    #[Groups(['read', 'write'])]
    private string $locale = 'fr';

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }
}
