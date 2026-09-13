<?php

namespace App\Infrastructure\Tenant;

class TenantContext
{
    private static ?int $currentSchoolId = null;

    public static function set(?int $schoolId): void
    {
        self::$currentSchoolId = $schoolId;
    }

    public static function setSchoolId(?int $schoolId): void
    {
        self::$currentSchoolId = $schoolId;
    }

    public static function get(): ?int
    {
        return self::$currentSchoolId;
    }

    public static function id(): ?int
    {
        return self::$currentSchoolId;
    }

    public static function hasTenant(): bool
    {
        return self::$currentSchoolId !== null && self::$currentSchoolId > 0;
    }

    public static function clear(): void
    {
        self::$currentSchoolId = null;
    }
}
