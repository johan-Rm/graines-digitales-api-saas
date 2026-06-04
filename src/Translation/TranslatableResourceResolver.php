<?php

namespace App\Translation;

use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\CategoryCode;
use App\Entity\CategoryCodeTranslation;
use App\Entity\MediaObject;
use App\Entity\MediaObjectTranslation;
use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;
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
        AccommodationTranslation::class => Accommodation::class,
        WebPageTranslation::class => WebPage::class,
        ArticleTranslation::class => Article::class,
        CategoryCodeTranslation::class => CategoryCode::class,
        MediaObjectTranslation::class => MediaObject::class,
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
        ]);

        if (!$resource instanceof TranslatableResource) {
            throw new NotFoundHttpException('Resource not found.');
        }

        return $resource;
    }
}
