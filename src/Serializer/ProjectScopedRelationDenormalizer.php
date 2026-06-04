<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Accommodation;
use App\Entity\Article;
use App\Entity\Attribute\InCodeSet;
use App\Entity\CategoryCode;
use App\Entity\MediaObject;
use App\Entity\Project;
use App\Entity\ProjectScopedResource;
use App\Entity\WebPage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Pre-processes POST/PUT payloads for ProjectScopedResource entities,
 * converting compact relation values to API Platform-compatible IRIs:
 *   - CategoryCode fields: accept codeValue (e.g. "local-commercial") or UUID → IRI
 *   - Other ProjectScopedResource fields: accept UUID → IRI
 *
 * Runs before API Platform's own denormalizer thanks to its high priority.
 */
final class ProjectScopedRelationDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'PROJECT_SCOPED_RELATION_DENORMALIZER_CALLED';

    /**
     * Maps entity classes to their API resource path segment.
     * Used to build IRIs without relying on IriConverter (which needs a full request context).
     */
    private const RESOURCE_PATHS = [
        CategoryCode::class  => 'category-codes',
        MediaObject::class   => 'media-objects',
        Accommodation::class => 'accommodations',
        WebPage::class       => 'web-pages',
        Article::class       => 'articles',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        if (is_array($data)) {
            $projectId = (string) ($context['uri_variables']['projectId'] ?? '');
            if (Uuid::isValid($projectId)) {
                $project = $this->entityManager->find(Project::class, Uuid::fromString($projectId));
                if ($project instanceof Project) {
                    $data = $this->preprocessRelations($data, $type, $project);
                }
            }

            // When updating an existing Accommodation, clear mutable collections before the
            // main normalizer adds new items — PropertyAccessor has no removeXxx methods,
            // so without this the Doctrine PersistentCollection accumulates stale entries.
            $existing = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;
            if ($existing instanceof Accommodation) {
                $existing->clearAmenityFeature();
                $existing->clearTags();
                $existing->clearAssociatedMedia();
                $existing->clearQualities();
            }
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_array($data)
            && is_a($type, ProjectScopedResource::class, true)
            && !isset($context[self::ALREADY_CALLED])
            && isset($context['uri_variables']['projectId']);
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ProjectScopedResource::class => false];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function preprocessRelations(array $data, string $entityClass, Project $project): array
    {
        if (!class_exists($entityClass)) {
            return $data;
        }

        $meta = $this->entityManager->getClassMetadata($entityClass);

        foreach (array_keys($meta->associationMappings) as $fieldName) {
            if (!array_key_exists($fieldName, $data)) {
                continue;
            }

            $targetClass = $meta->getAssociationTargetClass($fieldName);
            $value = $data[$fieldName];
            $inCodeSet = $this->readInCodeSet($entityClass, $fieldName);

            if ($meta->isSingleValuedAssociation($fieldName)) {
                $data[$fieldName] = $this->resolveRelationValue($value, $targetClass, $project, $inCodeSet);
            } elseif ($meta->isCollectionValuedAssociation($fieldName) && is_array($value) && array_is_list($value)) {
                $data[$fieldName] = $this->resolveCollectionValue($value, $targetClass, $project, $inCodeSet);
            }
        }

        return $data;
    }

    /**
     * @param list<mixed> $items
     *
     * @return list<mixed>
     */
    private function resolveCollectionValue(array $items, string $targetClass, Project $project, ?string $inCodeSet = null): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (is_array($item) && !array_is_list($item)) {
                $resolved[] = $this->preprocessRelations($item, $targetClass, $project);
            } else {
                $resolved[] = $this->resolveRelationValue($item, $targetClass, $project, $inCodeSet);
            }
        }

        return $resolved;
    }

    private function resolveRelationValue(mixed $value, string $targetClass, Project $project, ?string $inCodeSet = null): mixed
    {
        if (!is_string($value) || str_starts_with($value, '/')) {
            return $value;
        }

        $entity = $targetClass === CategoryCode::class
            ? $this->resolveCategoryCode($value, $project, $inCodeSet)
            : $this->resolveResourceByUuid($value, $targetClass);

        $path = self::RESOURCE_PATHS[$targetClass] ?? null;

        if (null === $entity || null === $path) {
            return $value;
        }

        return sprintf('/api/projects/%s/%s/%s', $project->getId(), $path, $entity->getId());
    }

    private function resolveCategoryCode(string $value, Project $project, ?string $inCodeSet = null): ?CategoryCode
    {
        $criteria = ['project' => $project];
        $criteria[Uuid::isValid($value) ? 'id' : 'codeValue'] = Uuid::isValid($value)
            ? Uuid::fromString($value)
            : $value;

        if (null !== $inCodeSet) {
            $criteria['inCodeSet'] = $inCodeSet;
        }

        return $this->entityManager->getRepository(CategoryCode::class)->findOneBy($criteria);
    }

    private function resolveResourceByUuid(string $value, string $targetClass): ?object
    {
        if (!Uuid::isValid($value)) {
            return null;
        }

        return $this->entityManager->find($targetClass, Uuid::fromString($value));
    }

    private function readInCodeSet(string $entityClass, string $fieldName): ?string
    {
        try {
            $attrs = (new \ReflectionProperty($entityClass, $fieldName))->getAttributes(InCodeSet::class);
        } catch (\ReflectionException) {
            return null;
        }

        return $attrs !== [] ? $attrs[0]->newInstance()->value : null;
    }
}
