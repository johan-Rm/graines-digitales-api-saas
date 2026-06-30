<?php

namespace App\Tests\Unit;

use App\Media\ImageWatermarker;
use PHPUnit\Framework\TestCase;

final class ImageWatermarkerTest extends TestCase
{
    public function testJpgAliasIsWatermarkable(): void
    {
        $watermarker = new ImageWatermarker();

        self::assertTrue($watermarker->isWatermarkable('image/jpeg'));
        self::assertTrue($watermarker->isWatermarkable('image/jpg'));
        self::assertTrue($watermarker->isWatermarkable('image/png'));
        self::assertTrue($watermarker->isWatermarkable('image/webp'));
        self::assertFalse($watermarker->isWatermarkable('image/gif'));
        self::assertFalse($watermarker->isWatermarkable('image/avif'));
    }
}
