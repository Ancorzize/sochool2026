<?php

namespace App\Domain\User\Enums;

enum SystemRoleEnum: string
{
    case PLATFORM_ADMIN = 'PLATFORM_ADMIN';
    case SCHOOL_ADMIN = 'SCHOOL_ADMIN';
    case TEACHER = 'TEACHER';
    case STUDENT = 'STUDENT';
    case GUARDIAN = 'GUARDIAN';
}
