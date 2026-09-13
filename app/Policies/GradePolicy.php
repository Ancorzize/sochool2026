<?php

namespace App\Policies;

use App\Domain\Grading\Models\StudentGrade;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\TenantContext;

class GradePolicy
{
    public function view(User $user, StudentGrade $grade): bool
    {
        return TenantContext::id() === $grade->school_id;
    }

    public function update(User $user, StudentGrade $grade): bool
    {
        if ($grade->assessment?->academicPeriod?->isLockedOrClosed()) {
            return false;
        }

        return TenantContext::id() === $grade->school_id;
    }

    public function delete(User $user, StudentGrade $grade): bool
    {
        if ($grade->assessment?->academicPeriod?->isLockedOrClosed()) {
            return false;
        }

        return TenantContext::id() === $grade->school_id;
    }
}
