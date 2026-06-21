<?php

namespace App\Tests\Unit;

use App\Entity\Project;
use App\Seed\ProjectSeedData;
use App\Seed\ProjectSeedWatermarkInstaller;
use PHPUnit\Framework\TestCase;

final class ProjectSeedWatermarkInstallerTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir().'/project_seed_watermark_'.bin2hex(random_bytes(6));
        mkdir($this->tempDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDirectory);
    }

    public function testInstallCopiesWatermarkFromSeedResourcesToWebapps(): void
    {
        $seedRoot = $this->tempDirectory.'/seed/mlk-modern-site-web';
        mkdir($seedRoot.'/resources', 0775, true);
        file_put_contents($seedRoot.'/resources/watermark4.png', 'wm');

        $webappsDirectory = $this->tempDirectory.'/webapps';
        $project = (new Project())
            ->setSlug('mlk-modern-site-web')
            ->setWatermarkPath('mlk-my-little-kasbah/public/watermark4.png');
        $data = new ProjectSeedData($seedRoot, 'fr', [], [], [], []);

        $installed = (new ProjectSeedWatermarkInstaller($webappsDirectory))->install($project, $data);

        self::assertTrue($installed);
        self::assertFileExists($webappsDirectory.'/mlk-my-little-kasbah/public/watermark4.png');
        self::assertSame('wm', file_get_contents($webappsDirectory.'/mlk-my-little-kasbah/public/watermark4.png'));
    }

    public function testInstallSkipsWhenProjectHasNoWatermarkPath(): void
    {
        $seedRoot = $this->tempDirectory.'/seed/mlk-modern-site-web';
        mkdir($seedRoot.'/resources', 0775, true);

        $project = (new Project())->setSlug('mlk-modern-site-web');
        $data = new ProjectSeedData($seedRoot, 'fr', [], [], [], []);

        $installed = (new ProjectSeedWatermarkInstaller($this->tempDirectory.'/webapps'))->install($project, $data);

        self::assertFalse($installed);
    }

    public function testInstallSkipsWhenSeedWatermarkIsMissing(): void
    {
        $seedRoot = $this->tempDirectory.'/seed/mlk-modern-site-web';
        mkdir($seedRoot.'/resources', 0775, true);

        $project = (new Project())
            ->setSlug('mlk-modern-site-web')
            ->setWatermarkPath('mlk-my-little-kasbah/public/watermark4.png');
        $data = new ProjectSeedData($seedRoot, 'fr', [], [], [], []);

        $installed = (new ProjectSeedWatermarkInstaller($this->tempDirectory.'/webapps'))->install($project, $data);

        self::assertFalse($installed);
        self::assertFileDoesNotExist($this->tempDirectory.'/webapps/mlk-my-little-kasbah/public/watermark4.png');
    }

    public function testInstallRejectsUnsafeWatermarkPath(): void
    {
        $seedRoot = $this->tempDirectory.'/seed/mlk-modern-site-web';
        mkdir($seedRoot.'/resources', 0775, true);

        $project = (new Project())
            ->setSlug('mlk-modern-site-web')
            ->setWatermarkPath('../escape/watermark4.png');
        $data = new ProjectSeedData($seedRoot, 'fr', [], [], [], []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsafe project watermarkPath configured');

        (new ProjectSeedWatermarkInstaller($this->tempDirectory.'/webapps'))->install($project, $data);
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
