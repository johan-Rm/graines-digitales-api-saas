<?php

namespace App\Translation;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\CategoryCode;
use App\Entity\CategoryCodeTranslation;
use App\Entity\CreativeWork;
use App\Entity\CreativeWorkTranslation;
use App\Entity\MenuItem;
use App\Entity\MenuItemTranslation;
use App\Entity\TranslatableResource;
use App\Entity\ResourceTranslation;
use App\Entity\WebPage;
use App\Entity\WebPageTranslation;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class TranslatableResourceResolver
{
    /**
     * @var array<class-string<ResourceTranslation>, class-string<TranslatableResource>>
     */
    private const ROOT_BY_TRANSLATION = [
        WebPageTranslation::class => WebPage::class,
        ArticleTranslation::class => Article::class,
        CreativeWorkTranslation::class => CreativeWork::class,
        MenuItemTranslation::class => MenuItem::class,
        CategoryCodeTranslation::class => CategoryCode::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectAccessGuard $projectAccessGuard,
    ) {
    }

    public function resolve(string $translationClass, array $uriVariables, bool $write): TranslatableResource
    {
        $rootClass = self::ROOT_BY_TRANSLATION[$translationClass] ?? null;
        if (null === $rootClass) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $resourceId = (string) ($uriVariables['id'] ?? '');
        $project = $write
            ? $this->projectAccessGuard->getWritableProject($projectId)
            : $this->projectAccessGuard->getReadableProject($projectId);

        if (!Uuid::isValid($resourceId)) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $resource = $this->entityManager->getRepository($rootClass)->findOneBy([
            'id' => Uuid::fromString($resourceId),
            'project' => $project,
            'deletedAt' => null,
        ]);

        if (!$resource instanceof TranslatableResource) {
            throw new NotFoundHttpException('Resource not found.');
        }

        return $resource;
    }
}
