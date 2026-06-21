<?php

namespace App\Seed;

use App\Entity\Project;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Delivers a project watermark from the seed source into the webapps directory.
 *
 * In prod the frontend "public/" assets are not synced into /srv/webapps, so the
 * watermark referenced by project.watermarkPath would be missing. The seed import
 * already mounts the seed project read-only and copies its assets into the webapps
 * directory, so it is the natural place to also install the watermark.
 *
 * Convention: the source file lives at "{seed}/resources/{basename(watermarkPath)}"
 * and is copied to "WEBAPPS_DIR/{watermarkPath}". Must run before media watermarking
 * so the file is present when images are processed.
 */
final readonly class ProjectSeedWatermarkInstaller
{
    public function __construct(
        #[Autowire(env: 'WEBAPPS_DIR')]
        private string $webappsDir,
    ) {
    }

    public function install(Project $project, ProjectSeedData $data): bool
    {
        $watermarkPath = $project->getWatermarkPath();
        if (null === $watermarkPath || '' === trim($watermarkPath)) {
            return false;
        }

        $normalizedWatermarkPath = ltrim(trim($watermarkPath), '/');
        if ('' === $normalizedWatermarkPath || str_contains($normalizedWatermarkPath, '..')) {
            throw new \RuntimeException(sprintf('Unsafe project watermarkPath configured: %s', $watermarkPath));
        }

        $source = rtrim($data->rootPath, '/').'/resources/'.basename($normalizedWatermarkPath);
        if (!is_file($source)) {
            return false;
        }

        $target = rtrim($this->webappsDir, '/').'/'.$normalizedWatermarkPath;
        $targetDirectory = dirname($target);

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create watermark target directory: %s', $targetDirectory));
        }

        if (!copy($source, $target)) {
            throw new \RuntimeException(sprintf('Unable to copy seed watermark "%s" to "%s".', $source, $target));
        }

        return true;
    }
}
