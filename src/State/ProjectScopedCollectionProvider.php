<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ProjectScopedResource;
use App\Entity\TranslatableResource;
use App\Security\ProjectAccessGuard;
use App\Translation\LocaleResolver;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectScopedCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
        private LocaleResolver $localeResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getReadableProject($projectId);
        $class = $operation->getClass();

        if (is_a($class, ProjectScopedResource::class, true)) {
            $resources = $this->entityManager->getRepository($class)->findBy([
                'project' => $project,
            ]);

            foreach ($resources as $resource) {
                if ($resource instanceof TranslatableResource) {
                    $this->resolveTranslationLocale($resource);
                }
            }

            return $resources;
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }

    private function resolveTranslationLocale(TranslatableResource $resource): void
    {
        $project = $resource->getProject();
        if (null === $project) {
            return;
        }

        $requestedLocale = $this->localeResolver->resolve($project);
        $currentLocale = null !== $resource->getTranslation($requestedLocale)
            ? $requestedLocale
            : $project->getSourceLocale();
        $fallbackLocale = $currentLocale !== $requestedLocale ? $currentLocale : null;

        $resource->setResolvedLocale($requestedLocale, $currentLocale, $fallbackLocale);
    }
}
