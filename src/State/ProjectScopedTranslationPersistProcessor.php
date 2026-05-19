<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ResourceTranslation;
use App\Security\CurrentUserProvider;
use App\Translation\TranslatableResourceResolver;

final readonly class ProjectScopedTranslationPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private TranslatableResourceResolver $resourceResolver,
        private CurrentUserProvider $currentUserProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ResourceTranslation) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $class = $operation->getClass();
        $resource = is_string($class)
            ? $this->resourceResolver->resolve($class, $uriVariables, true)
            : null;

        $locale = (string) ($uriVariables['locale'] ?? '');
        $translation = $resource?->getTranslation($locale) ?? $data;
        $translation->setLocale($locale);
        $translation->setTranslatable($resource);
        $translation->touch($this->currentUserProvider->getUser());

        return $this->decorated->process($translation, $operation, $uriVariables, $context);
    }
}
