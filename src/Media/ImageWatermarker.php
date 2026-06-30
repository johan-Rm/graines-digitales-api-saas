<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Pure image operation: composites a watermark over a source image and writes
 * the result to a target path. Shared by the async watermark handler (HTTP
 * uploads) and the CLI seed importer so the visual rendering stays identical.
 */
final readonly class ImageWatermarker
{
    private const WATERMARK_RATIO = 0.22;
    private const WATERMARK_SCALE = 0.6;
    private const WATERMARK_OPACITY = 0.45;
    private const JPEG_QUALITY = 95;

    /**
     * @return bool true if the watermark was applied, false if the format is not
     *              a watermarkable raster image (caller should fall back to a copy)
     */
    public function isWatermarkable(?string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true);
    }

    public function apply(string $sourcePath, string $watermarkPath, string $targetPath): void
    {
        $image = new \Imagick($sourcePath);
        $image->autoOrient();

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
}
