<?php

namespace App\Enum;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canWrite(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Editor], true);
    }
}
