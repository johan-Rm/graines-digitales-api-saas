<?php

namespace App\Tests\Unit;

use App\Entity\Project;
use App\Seed\ProjectSeedData;
use App\Seed\ProjectSeedImageSynchronizer;
use PHPUnit\Framework\TestCase;

final class ProjectSeedImageSynchronizerTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir().'/project_seed_image_sync_'.bin2hex(random_bytes(6));
        mkdir($this->tempDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDirectory);
    }

    public function testSyncClearsProjectMediaDirectoryAndCopiesAllSeedImages(): void
    {
        $seedRoot = $this->tempDirectory.'/seed/blue-bay-mogador';
        $imagesDirectory = $seedRoot.'/resources/images/gallery';
        $webappsDirectory = $this->tempDirectory.'/webapps';
        $targetDirectory = $webappsDirectory.'/blue-bay';

        mkdir($imagesDirectory, 0775, true);
        mkdir($targetDirectory.'/old', 0775, true);
        file_put_contents($seedRoot.'/resources/images/hero.jpg', 'hero');
        file_put_contents($imagesDirectory.'/room.jpg', 'room');
        file_put_contents($targetDirectory.'/old/stale.jpg', 'stale');

        $project = (new Project())
            ->setSlug('blue-bay-mogador')
            ->setMediaPath('blue-bay');
        $data = new ProjectSeedData($seedRoot, 'fr', [], [], [], []);

        $count = (new ProjectSeedImageSynchronizer($webappsDirectory))->sync($project, $data);

        self::assertSame(2, $count);
        self::assertFileExists($targetDirectory.'/hero.jpg');
        self::assertFileExists($targetDirectory.'/gallery/room.jpg');
        self::assertFileExists($targetDirectory.'/originals/hero.jpg');
        self::assertFileExists($targetDirectory.'/originals/gallery/room.jpg');
        self::assertFileDoesNotExist($targetDirectory.'/old/stale.jpg');
    }

    private function removeDirectory(string $directory): void
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
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
