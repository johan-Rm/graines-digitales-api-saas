<?php

namespace App\Tests\Unit;

use App\Seed\ProjectSeedAccommodationMediaIndex;
use App\Seed\ProjectSeedData;
use PHPUnit\Framework\TestCase;

final class ProjectSeedAccommodationMediaIndexTest extends TestCase
{
    public function testCoversImagesReferencedByAccommodations(): void
    {
        $index = ProjectSeedAccommodationMediaIndex::fromSeedData($this->seedData([
            $this->accommodation(
                associatedMedia: [['image' => '/villa/hero.jpg']],
                hasPartMedia: ['/villa/kitchen.jpg'],
            ),
        ]));

        // direct associatedMedia reference, full normalized path
        self::assertTrue($index->covers('villa/hero.jpg'));
        // hasPart content-block reference
        self::assertTrue($index->covers('villa/kitchen.jpg'));
        // basename and "media-" prefix variants resolve to the same image
        self::assertTrue($index->covers('hero'));
        self::assertTrue($index->covers('media-kitchen'));
    }

    public function testDoesNotCoverUnreferencedImages(): void
    {
        $index = ProjectSeedAccommodationMediaIndex::fromSeedData($this->seedData([
            $this->accommodation(associatedMedia: [['image' => '/villa/hero.jpg']]),
        ]));

        self::assertFalse($index->covers('logo.png'));
        self::assertFalse($index->covers('pages/about.jpg'));
    }

    public function testEmptyWhenNoAccommodations(): void
    {
        $index = ProjectSeedAccommodationMediaIndex::fromSeedData($this->seedData([]));

        self::assertFalse($index->covers('villa/hero.jpg'));
    }

    /**
     * @param list<array<string, mixed>> $accommodations
     */
    private function seedData(array $accommodations): ProjectSeedData
    {
        return new ProjectSeedData('/seed', 'fr', [], [], [], $accommodations);
    }

    /**
     * @param list<array{image: string}> $associatedMedia
     * @param list<string>                $hasPartMedia
     *
     * @return array<string, mixed>
     */
    private function accommodation(array $associatedMedia = [], array $hasPartMedia = []): array
    {
        return [
            'associatedMedia' => array_map(
                static fn (array $edge): array => $edge + ['caption' => null, 'keywords' => [], 'representativeOfPage' => null],
                $associatedMedia,
            ),
            'hasPart' => [] === $hasPartMedia ? [] : [[
                'additionalType' => 'Section',
                'position' => null,
                'name' => null,
                'headline' => null,
                'text' => null,
                'associatedMedia' => $hasPartMedia,
                'meta' => [],
            ]],
        ];
    }
}
