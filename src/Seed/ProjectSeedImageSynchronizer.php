<?php

namespace App\Seed;

use App\Entity\Project;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ProjectSeedImageSynchronizer
{
    public function __construct(
        #[Autowire(env: 'WEBAPPS_DIR')]
        private string $webappsDir,
    ) {
    }

    public function sync(Project $project, ProjectSeedData $data): int
    {
        $mediaPath = $project->getMediaPath();
        if (null === $mediaPath || '' === trim($mediaPath)) {
            throw new \RuntimeException(sprintf('Project "%s" has no mediaPath configured.', $project->getSlug()));
        }

        $sourceDirectory = rtrim($data->rootPath, '/').'/resources/images';
        if (!is_dir($sourceDirectory)) {
            throw new \RuntimeException(sprintf('Missing seed images directory: %s', $sourceDirectory));
        }

        $targetDirectory = $this->targetDirectory($mediaPath);
        $originalsDirectory = $targetDirectory.'/originals';

        $this->clearDirectory($targetDirectory);
        $this->ensureDirectory($targetDirectory);
        $this->ensureDirectory($originalsDirectory);

        $count = 0;
        foreach ($this->sourceFiles($sourceDirectory) as $sourcePath => $relativePath) {
            $this->copyFile($sourcePath, $targetDirectory.'/'.$relativePath);
            $this->copyFile($sourcePath, $originalsDirectory.'/'.$relativePath);
            $count++;
        }

        return $count;
    }

    private function targetDirectory(string $mediaPath): string
    {
        $normalizedMediaPath = trim($mediaPath, '/');
        if ('' === $normalizedMediaPath || str_contains($normalizedMediaPath, '..')) {
            throw new \RuntimeException(sprintf('Unsafe project mediaPath configured: %s', $mediaPath));
        }

        return rtrim($this->webappsDir, '/').'/'.$normalizedMediaPath;
    }

    private function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                if (!rmdir($file->getPathname())) {
                    throw new \RuntimeException(sprintf('Unable to remove directory: %s', $file->getPathname()));
                }

                continue;
            }

            if (!unlink($file->getPathname())) {
                throw new \RuntimeException(sprintf('Unable to remove file: %s', $file->getPathname()));
            }
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory: %s', $directory));
        }
    }

    /**
     * @return \Generator<string, string>
     */
    private function sourceFiles(string $sourceDirectory): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $sourcePath = $file->getPathname();
            $relativePath = substr($sourcePath, strlen(rtrim($sourceDirectory, '/')) + 1);
            if (false === $relativePath || '' === $relativePath) {
                continue;
            }

            yield $sourcePath => $relativePath;
        }
    }

    private function copyFile(string $sourcePath, string $targetPath): void
    {
        $targetDirectory = dirname($targetPath);
        $this->ensureDirectory($targetDirectory);

        if (!copy($sourcePath, $targetPath)) {
            throw new \RuntimeException(sprintf('Unable to copy seed image "%s" to "%s".', $sourcePath, $targetPath));
        }
    }
}
