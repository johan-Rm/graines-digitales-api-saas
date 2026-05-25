<?php

namespace App\Enum;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Billing = 'billing';

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canCreateProject(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
