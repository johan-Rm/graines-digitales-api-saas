<?php

namespace App\MessageHandler;

use App\Entity\MediaObject;
use App\Entity\Project;
use App\Enum\MediaStatus;
use App\Message\AnalyzeMediaObject;
use App\Message\WatermarkMediaObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsMessageHandler]
final class WatermarkMediaObjectHandler
{
    private const WATERMARK_RATIO = 0.22;
    private const WATERMARK_SCALE = 0.6;
    private const WATERMARK_OPACITY = 0.20;
    private const JPEG_QUALITY = 95;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        #[Autowire(env: 'WEBAPPS_DIR')]
        private readonly string $webappsDir,
    ) {
    }

    public function __invoke(WatermarkMediaObject $message): void
    {
        $mediaObject = $this->entityManager->find(MediaObject::class, $message->mediaObjectId);
        if (!$mediaObject instanceof MediaObject) {
            return;
        }

        $project = $mediaObject->getProject();
        $currentFilename = $mediaObject->getFilePath();

        if (!$project instanceof Project || null === $project->getMediaPath() || null === $currentFilename) {
            $this->finalize($mediaObject);
            return;
        }

        $webappsDir = rtrim($this->webappsDir, '/');
        $mediaPath = $project->getMediaPath();
        $originalFile = $webappsDir.'/'.$mediaPath.'/originals/'.$currentFilename;

        if (!file_exists($originalFile)) {
            $this->finalize($mediaObject);
            return;
        }

        $newFilename = $this->buildSlugFilename($mediaObject, $currentFilename);
        $targetFile = $webappsDir.'/'.$mediaPath.'/'.$newFilename;

        $watermarkPath = $project->getWatermarkPath();
        if (null !== $watermarkPath) {
            $watermarkFile = $webappsDir.'/'.$watermarkPath;
            if (file_exists($watermarkFile)) {
                $this->applyWatermark($originalFile, $watermarkFile, $targetFile);
            } else {
                copy($originalFile, $targetFile);
            }
        } else {
            copy($originalFile, $targetFile);
        }

        // Remove the unprocessed UUID file left by VichUploader
        $tempFile = $webappsDir.'/'.$mediaPath.'/'.$currentFilename;
        if (file_exists($tempFile) && $tempFile !== $targetFile) {
            unlink($tempFile);
        }

        $mediaObject->setFilePath($newFilename);
        $this->finalize($mediaObject);
    }

    private function buildSlugFilename(MediaObject $mediaObject, string $currentFilename): string
    {
        $ext = strtolower(pathinfo($currentFilename, PATHINFO_EXTENSION));

        $baseName = $mediaObject->getFinalFilename()
            ?? $mediaObject->getFinalAlt()
            ?? $mediaObject->getOriginalFilename()
            ?? 'image';

        $baseName = pathinfo($baseName, PATHINFO_FILENAME);
        $shortId = substr((string) $mediaObject->getId(), 0, 8);

        $slug = (new AsciiSlugger())->slug($baseName)->lower()->toString();

        return $slug.'-'.$shortId.'.'.$ext;
    }

    private function applyWatermark(string $sourcePath, string $watermarkPath, string $targetPath): void
    {
        $image = new \Imagick($sourcePath);
        $image->autoOrientImage();

        $watermark = new \Imagick($watermarkPath);
        $wmWidth = (int) round($image->getImageWidth() * self::WATERMARK_RATIO * self::WATERMARK_SCALE);

        $watermark->resizeImage($wmWidth, 0, \Imagick::FILTER_LANCZOS, 1);
        $watermark->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        $watermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, self::WATERMARK_OPACITY, \Imagick::CHANNEL_ALPHA);

        $x = (int) (($image->getImageWidth() - $watermark->getImageWidth()) / 2);
        $y = (int) (($image->getImageHeight() - $watermark->getImageHeight()) / 2);
        $image->compositeImage($watermark, \Imagick::COMPOSITE_OVER, $x, $y);
        $image->setImageCompressionQuality(self::JPEG_QUALITY);
        $image->writeImage($targetPath);

        $image->destroy();
        $watermark->destroy();
    }

    private function finalize(MediaObject $mediaObject): void
    {
        $mediaObject->setStatus(MediaStatus::PendingAnalysis);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new AnalyzeMediaObject($mediaObject->getId()));
    }
}
