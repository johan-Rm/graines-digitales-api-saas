<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ResourceTranslation;
use App\Translation\TranslatableResourceResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ProjectScopedTranslationItemProvider implements ProviderInterface
{
    public function __construct(private TranslatableResourceResolver $resourceResolver)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ResourceTranslation
    {
        $class = $operation->getClass();
        if (!is_string($class) || !is_a($class, ResourceTranslation::class, true)) {
            throw new NotFoundHttpException('Translation not found.');
        }

        $locale = (string) ($uriVariables['locale'] ?? '');
        $translation = $this->resourceResolver->resolve($class, $uriVariables, false)->getTranslation($locale);

        if (!$translation instanceof ResourceTranslation && $operation instanceof Put) {
            /** @var ResourceTranslation $translation */
            $translation = new $class();
        }

        if (!$translation instanceof ResourceTranslation) {
            throw new NotFoundHttpException('Translation not found.');
        }

        return $translation;
    }
}
