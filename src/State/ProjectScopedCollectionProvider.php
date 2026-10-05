<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ProjectScopedResource;
use App\Entity\TranslatableResource;
use App\Security\ProjectAccessGuard;
use App\Translation\LocaleResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

final readonly class ProjectScopedCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
        private LocaleResolver $localeResolver,
        private Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getReadableProject($projectId);
        $class = $operation->getClass();

        if (!is_a($class, ProjectScopedResource::class, true)) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        // Stable ordering is required for consistent pagination (no skipped/duplicated rows across pages).
        $queryBuilder = $this->entityManager->getRepository($class)->createQueryBuilder('resource')
            ->andWhere('resource.project = :project')
            ->setParameter('project', $project)
            ->orderBy('resource.createdAt', 'DESC')
            ->addOrderBy('resource.id', 'ASC');

        if (!$this->pagination->isEnabled($operation, $context)) {
            $resources = $queryBuilder->getQuery()->getResult();
            $this->resolveLocales($resources);

            return $resources;
        }

        [, $offset, $limit] = $this->pagination->getPagination($operation, $context);
        $queryBuilder->setFirstResult($offset)->setMaxResults($limit);

        $paginator = new Paginator(new DoctrinePaginator($queryBuilder->getQuery()));
        $this->resolveLocales($paginator);

        return $paginator;
    }

    /**
     * @param iterable<object> $resources
     */
    private function resolveLocales(iterable $resources): void
    {
        foreach ($resources as $resource) {
            if ($resource instanceof TranslatableResource) {
                $this->resolveTranslationLocale($resource);
            }
        }
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
