<?php

namespace App\Seed;

use App\Entity\MediaObject;
use App\Entity\Project;
use App\Entity\ResourceTranslation;
use App\Entity\WebPage;
use App\Entity\WebPageMedia;
use App\Enum\ContentStatus;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class WebPageSeedImporter implements ProjectSeedImporterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResourceAutoFiller $autoFiller,
        private ResourceAutoTranslator $autoTranslator,
    ) {
    }

    public function family(): string
    {
        return ProjectSeedFamily::WEB_PAGES;
    }

    public function requires(ProjectSeedData $data): array
    {
        foreach ($data->webPages as $webPage) {
            if ([] !== $webPage['associatedMedia']) {
                return [ProjectSeedFamily::MEDIA];
            }
        }

        return [];
    }

    public function import(Project $project, ProjectSeedData $data): int
    {
        $count = 0;
        $imported = [];

        foreach ($data->webPages as $source) {
            $webPage = $this->findWebPage($project, $source['slug'], $source['locale']);
            if (!$webPage instanceof WebPage) {
                $webPage = (new WebPage())->setProject($project);
            }

            $webPage->setCurrentLocale($source['locale']);
            $webPage
                ->setSlug($source['slug'])
                ->setMetaTitle($source['metaTitle'])
                ->setMetaDescription($source['metaDescription'])
                ->setHeadline($source['headline'])
                ->setBody($source['body'])
                ->setStatus(ContentStatus::Published)
                ->clearAssociatedMedia();

            foreach ($source['associatedMedia'] as $index => $reference) {
                $mediaObject = $this->findMediaObject($project, $reference);
                if (!$mediaObject instanceof MediaObject) {
                    throw new \RuntimeException(sprintf('Media reference "%s" was not found while importing web page "%s".', $reference, $source['slug']));
                }

                $webPage->addAssociatedMedia(
                    (new WebPageMedia())
                        ->setMediaObject($mediaObject)
                        ->setPosition($index + 1),
                );
            }

            $this->entityManager->persist($webPage);

            $translation = $webPage->getTranslation($source['locale']);
            if ($translation instanceof ResourceTranslation) {
                $this->autoFiller->fill($translation);
            }

            $imported[] = $webPage;
            $count++;
        }

        // Flush source-locale data, then refresh each entity so that Doctrine's
        // collection is fully re-initialized from DB (avoids partial-init from the
        // JOIN used in findWebPage, which would cause duplicate-translation inserts).
        $this->entityManager->flush();
        foreach ($imported as $webPage) {
            $this->entityManager->refresh($webPage);
            $this->autoTranslator->synchronize($webPage, $project);
        }

        return $count;
    }

    private function findWebPage(Project $project, string $slug, string $locale): ?WebPage
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('webPage')
            ->from(WebPage::class, 'webPage')
            ->join('webPage.translations', 'translation')
            ->where('webPage.project = :project')
            ->andWhere('translation.slug = :slug')
            ->andWhere('translation.locale = :locale')
            ->setParameter('project', $project)
            ->setParameter('slug', $slug)
            ->setParameter('locale', $locale)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof WebPage ? $result : null;
    }

    private function findMediaObject(Project $project, string $reference): ?MediaObject
    {
        $filename = $this->normalizeMediaReference($reference);
        if (str_starts_with($filename, 'media-') || !str_contains(basename($filename), '.')) {
            $filename = str_starts_with($filename, 'media-') ? substr($filename, 6) : $filename;

            $result = $this->entityManager->createQueryBuilder()
                ->select('mediaObject')
                ->from(MediaObject::class, 'mediaObject')
                ->where('mediaObject.project = :project')
                ->andWhere('mediaObject.filePath LIKE :basename')
                ->setParameter('project', $project)
                ->setParameter('basename', $filename.'.%')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $result instanceof MediaObject ? $result : null;
        }

        $mediaObject = $this->entityManager->getRepository(MediaObject::class)->findOneBy([
            'project' => $project,
            'filePath' => $filename,
        ]);

        return $mediaObject instanceof MediaObject ? $mediaObject : null;
    }

    private function normalizeMediaReference(string $reference): string
    {
        return str_starts_with($reference, 'media-') ? $reference : ltrim($reference, '/');
    }
}
