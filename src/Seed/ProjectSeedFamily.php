<?php

namespace App\Seed;

final class ProjectSeedFamily
{
    public const METADATA = 'metadata';
    public const MEDIA = 'media';
    public const WEB_PAGES = 'web-pages';
    public const ACCOMMODATIONS = 'accommodations';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::METADATA,
            self::MEDIA,
            self::WEB_PAGES,
            self::ACCOMMODATIONS,
        ];
    }
}
