<?php

namespace App\Tests\Unit;

use App\Entity\MediaObject;
use App\Entity\Project;
use App\Media\MediaContentUrlResolver;
use PHPUnit\Framework\TestCase;

final class MediaContentUrlResolverTest extends TestCase
{
    private MediaContentUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MediaContentUrlResolver();
    }

    public function testItBuildsRelativeImageUrl(): void
    {
        $project = (new Project())->setMediaBaseUrl('/images');
        $mediaObject = (new MediaObject())
            ->setProject($project)
            ->setFilePath('riad-patio.jpg');

        self::assertSame('/images/riad-patio.jpg', $this->resolver->resolve($mediaObject));
    }

    public function testItIgnoresLegacyAbsoluteBaseUrl(): void
    {
        $project = (new Project())->setMediaBaseUrl('http://localhost:3000/images');
        $mediaObject = (new MediaObject())
            ->setProject($project)
            ->setFilePath('riad-patio.jpg');

        self::assertSame('/images/riad-patio.jpg', $this->resolver->resolve($mediaObject));
    }

    public function testItReturnsNullWithoutFilePath(): void
    {
        self::assertNull($this->resolver->resolve(new MediaObject()));
    }
}
