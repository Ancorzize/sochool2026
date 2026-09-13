<?php

namespace App\Infrastructure\Tenant;

class CampusContext
{
    private static ?int $currentCampusId = null;

    public static function set(?int $campusId): void
    {
        self::$currentCampusId = $campusId;
    }

    public static function id(): ?int
    {
        return self::$currentCampusId;
    }

    public static function clear(): void
    {
        self::$currentCampusId = null;
    }
}
