<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ProjectScopedResource;
use App\Entity\TranslatableResource;
use App\Security\ProjectAccessGuard;
use App\Translation\LocaleResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class ProjectScopedItemProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
        private LocaleResolver $localeResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $id = (string) ($uriVariables['id'] ?? '');
        $project = $this->projectAccessGuard->getReadableProject($projectId);
        $class = $operation->getClass();

        if (is_a($class, ProjectScopedResource::class, true)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException('Resource not found.');
            }

            $resource = $this->entityManager->getRepository($class)->findOneBy([
                'id' => Uuid::fromString($id),
                'project' => $project,
                'deletedAt' => null,
            ]);

            if (!$resource instanceof ProjectScopedResource) {
                throw new NotFoundHttpException('Resource not found.');
            }

            if ($resource instanceof TranslatableResource) {
                $isWrite = $operation instanceof Put || $operation instanceof Post || $operation instanceof Patch;
                $this->resolveTranslationLocale($resource, $isWrite);
            }

            return $resource;
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }

    private function resolveTranslationLocale(TranslatableResource $resource, bool $isWrite = false): void
    {
        $project = $resource->getProject();
        if (null === $project) {
            return;
        }

        $requestedLocale = $this->localeResolver->resolve($project);

        if ($isWrite) {
            $resource->setResolvedLocale($requestedLocale, $requestedLocale, null);

            return;
        }

        $currentLocale = null !== $resource->getTranslation($requestedLocale)
            ? $requestedLocale
            : $project->getSourceLocale();
        $fallbackLocale = $currentLocale !== $requestedLocale ? $currentLocale : null;

        $resource->setResolvedLocale($requestedLocale, $currentLocale, $fallbackLocale);
    }
}
