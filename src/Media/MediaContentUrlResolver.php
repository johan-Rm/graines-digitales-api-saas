<?php

namespace App\Media;

use App\Entity\MediaObject;

final class MediaContentUrlResolver
{
    private const DEFAULT_BASE_PATH = '/images';

    public function resolve(MediaObject $mediaObject): ?string
    {
        $filePath = $mediaObject->getFilePath();
        if (null === $filePath || '' === trim($filePath)) {
            return null;
        }

        $configuredBasePath = $mediaObject->getProject()?->getMediaBaseUrl();
        $basePath = is_string($configuredBasePath) && $this->isImageBasePath($configuredBasePath)
            ? $configuredBasePath
            : self::DEFAULT_BASE_PATH;

        return rtrim($basePath, '/').'/'.ltrim($filePath, '/');
    }

    private function isImageBasePath(string $basePath): bool
    {
        return self::DEFAULT_BASE_PATH === $basePath
            || str_starts_with($basePath, self::DEFAULT_BASE_PATH.'/');
    }
}
