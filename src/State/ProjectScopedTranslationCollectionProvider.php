<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ResourceTranslation;
use App\Translation\TranslatableResourceResolver;

final readonly class ProjectScopedTranslationCollectionProvider implements ProviderInterface
{
    public function __construct(private TranslatableResourceResolver $resourceResolver)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $class = $operation->getClass();
        if (!is_string($class) || !is_a($class, ResourceTranslation::class, true)) {
            return [];
        }

        return $this->resourceResolver->resolve($class, $uriVariables, false)->getTranslations();
    }
}
