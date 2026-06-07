<?php

namespace App\Seed;

use App\Entity\MediaObject;
use App\Entity\Project;
use App\Enum\AiReviewStatus;
use App\Enum\MediaStatus;
use App\Media\MediaCaptionGuesser;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MediaObjectSeedImporter implements ProjectSeedImporterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResourceAutoTranslator $autoTranslator,
        private MediaCaptionGuesser $mediaCaptionGuesser,
        private MediaObjectSeedCaptionApplier $captionApplier,
        #[Autowire(env: 'WEBAPPS_DIR')]
        private string $webappsDir,
    ) {
    }

    public function family(): string
    {
        return ProjectSeedFamily::MEDIA;
    }

    public function requires(ProjectSeedData $data): array
    {
        return [];
    }

    public function import(Project $project, ProjectSeedData $data): int
    {
        $count = 0;

        $imported = [];

        foreach ($data->mediaObjects as $source) {
            $mediaObject = $this->entityManager->getRepository(MediaObject::class)->findOneBy([
                'project' => $project,
                'filePath' => $source['filename'],
            ]);

            if (!$mediaObject instanceof MediaObject) {
                $mediaObject = (new MediaObject())
                    ->setProject($project)
                    ->setFilePath($source['filename']);
            }

            $sourceCaption = $source['caption'] ?? null;
            $caption = is_string($sourceCaption) && '' !== trim($sourceCaption)
                ? $sourceCaption
                : ($this->mediaCaptionGuesser->guessFromFilename($source['filename']) ?? $source['filename']);
            $this->copyMediaFile($project, $source['sourcePath'], $source['filename']);
            $this->captionApplier->apply($mediaObject, $data->locale, $caption);
            $mediaObject
                ->setOriginalFilename($source['filename'])
                ->setFinalFilename($source['filename'])
                ->setChecksumSha256($source['checksum'])
                ->setMimeType($source['mimeType'])
                ->setSize($source['size'])
                ->setMainEntity($source['mainEntity'])
                ->setFinalAlt($caption)
                ->setFinalDescription($caption)
                ->setStatus(MediaStatus::Ready)
                ->setAiReviewStatus(AiReviewStatus::NotRequested);

            $this->entityManager->persist($mediaObject);
            $imported[] = $mediaObject;
            $count++;
        }

        // Flush source-locale data before creating translations for other locales,
        // so that lazy-loaded collections are consistent when synchronize runs.
        $this->entityManager->flush();
        foreach ($imported as $mediaObject) {
            $this->autoTranslator->synchronize($mediaObject, $project);
        }

        return $count;
    }

    private function copyMediaFile(Project $project, string $sourcePath, string $filename): void
    {
        $mediaPath = $project->getMediaPath();
        if (null === $mediaPath || '' === trim($mediaPath)) {
            throw new \RuntimeException(sprintf('Project "%s" has no mediaPath configured.', $project->getSlug()));
        }

        $targetDirectory = rtrim($this->webappsDir, '/').'/'.trim($mediaPath, '/');
        $originalsDirectory = $targetDirectory.'/originals';

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create media target directory: %s', $targetDirectory));
        }

        if (!is_dir($originalsDirectory) && !mkdir($originalsDirectory, 0775, true) && !is_dir($originalsDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create media originals directory: %s', $originalsDirectory));
        }

        foreach ([$targetDirectory.'/'.$filename, $originalsDirectory.'/'.$filename] as $targetPath) {
            if (!copy($sourcePath, $targetPath)) {
                throw new \RuntimeException(sprintf('Unable to copy seed media "%s" to "%s".', $sourcePath, $targetPath));
            }
        }
    }
}
