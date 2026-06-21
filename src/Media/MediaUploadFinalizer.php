<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\MediaObject;
use App\Message\WatermarkMediaObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Post-persist media pipeline shared by every upload entry point
 * (single POST processor and bulk upload controller): the uploaded file is
 * copied to the project "originals" directory, then watermarking is dispatched.
 * The watermark handler chains analysis once it has finished, so callers must
 * not dispatch AnalyzeMediaObject themselves.
 */
final readonly class MediaUploadFinalizer
{
    public function __construct(
        private MessageBusInterface $messageBus,
        #[Autowire(env: 'WEBAPPS_DIR')]
        private string $webappsDir,
    ) {
    }

    public function finalize(MediaObject $mediaObject): void
    {
        if (null === $mediaObject->getFilePath()) {
            return;
        }

        $this->copyToOriginals($mediaObject);
        $this->messageBus->dispatch(new WatermarkMediaObject((string) $mediaObject->getId()));
    }

    private function copyToOriginals(MediaObject $mediaObject): void
    {
        $mediaPath = $mediaObject->getProject()?->getMediaPath();
        if (null === $mediaPath) {
            return;
        }

        $webappsDir = rtrim($this->webappsDir, '/');
        $source = $webappsDir.'/'.$mediaPath.'/'.$mediaObject->getFilePath();
        $originalsDir = $webappsDir.'/'.$mediaPath.'/originals';

        if (!file_exists($source)) {
            return;
        }

        $target = $originalsDir.'/'.$mediaObject->getFilePath();
        $targetDir = dirname($target);

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        if (!is_readable($source) || !is_writable($targetDir)) {
            return;
        }

        @copy($source, $target);
    }
}
