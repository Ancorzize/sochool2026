<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Models\Classroom;

class ClassroomService
{
    public function listClassrooms(int $schoolId, ?int $campusId = null)
    {
        $query = Classroom::where('school_id', $schoolId);
        if ($campusId) {
            $query->where('campus_id', $campusId);
        }
        return $query->orderBy('id')->get();
    }

    public function createClassroom(int $schoolId, array $data): Classroom
    {
        $data['school_id'] = $schoolId;
        return Classroom::create($data);
    }
}
