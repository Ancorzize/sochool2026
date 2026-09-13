<?php

namespace App\Policies;

use App\Domain\Student\Models\Student;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\TenantContext;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        return TenantContext::id() === $student->school_id;
    }

    public function update(User $user, Student $student): bool
    {
        return TenantContext::id() === $student->school_id;
    }

    public function delete(User $user, Student $student): bool
    {
        return TenantContext::id() === $student->school_id;
    }
}
