<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function canManageProjects(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Member]);
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin]);
    }

    public function canDeleteWorkspace(): bool
    {
        return $this === self::Owner;
    }

    public function canView(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
