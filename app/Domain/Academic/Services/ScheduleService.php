<?php

namespace App\Domain\Academic\Services;

use App\Domain\Academic\Models\Schedule;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleService
{
    public function listSchedules(int $schoolId, ?int $campusId = null, ?int $courseId = null, ?int $teacherId = null, ?int $classroomId = null)
    {
        $query = Schedule::where('school_id', $schoolId)->where('status', 'ACTIVE');

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }
        if ($courseId) {
            $query->where('course_id', $courseId);
        }
        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }
        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        return $query->orderBy('day_of_week')->orderBy('start_time')->get();
    }

    public function createSchedule(int $schoolId, array $data): Schedule
    {
        $data['school_id'] = $schoolId;
        $data['status'] = $data['status'] ?? 'ACTIVE';

        $validFrom = $data['valid_from'];
        $validUntil = $data['valid_until'] ?? null;
        $dayOfWeek = (int) $data['day_of_week'];
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];

        if ($startTime >= $endTime) {
            throw new InvalidArgumentException("start_time must be strictly less than end_time.");
        }

        // Helper for temporal overlap
        $dateOverlapCondition = function ($query) use ($validFrom, $validUntil) {
            if ($validUntil === null) {
                $query->where(function ($q) use ($validFrom) {
                    $q->whereNull('valid_until')
                      ->orWhere('valid_until', '>=', $validFrom);
                });
            } else {
                $query->where(function ($q) use ($validFrom, $validUntil) {
                    $q->where(function ($sub) use ($validFrom, $validUntil) {
                        $sub->where('valid_from', '<=', $validUntil)
                            ->where(function ($inner) use ($validFrom) {
                                $inner->whereNull('valid_until')
                                      ->orWhere('valid_until', '>=', $validFrom);
                            });
                    });
                });
            }
        };

        // 1. Teacher Conflict (Cross-Campus Included)
        $teacherConflict = DB::table('schedules')
            ->where('school_id', $schoolId)
            ->where('teacher_id', $data['teacher_id'])
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'ACTIVE')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where($dateOverlapCondition)
            ->exists();

        if ($teacherConflict) {
            throw new InvalidArgumentException("Docente no disponible en esa franja horaria.");
        }

        // 2. Course Conflict
        $courseConflict = DB::table('schedules')
            ->where('school_id', $schoolId)
            ->where('course_id', $data['course_id'])
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'ACTIVE')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where($dateOverlapCondition)
            ->exists();

        if ($courseConflict) {
            throw new InvalidArgumentException("El curso ya posee una materia asignada en esa franja horaria.");
        }

        // 3. Classroom Conflict (if classroom provided)
        if (!empty($data['classroom_id'])) {
            $classroomConflict = DB::table('schedules')
                ->where('school_id', $schoolId)
                ->where('classroom_id', $data['classroom_id'])
                ->where('day_of_week', $dayOfWeek)
                ->where('status', 'ACTIVE')
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->where($dateOverlapCondition)
                ->exists();

            if ($classroomConflict) {
                throw new InvalidArgumentException("El aula seleccionada ya está ocupada en esa franja horaria.");
            }
        }

        return Schedule::create($data);
    }

    public function delete(int $schoolId, int $id, ?int $campusId = null): Schedule
    {
        $query = Schedule::where('id', $id)->where('school_id', $schoolId);

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        $schedule = $query->first();

        if (!$schedule) {
            throw new InvalidArgumentException("Horario no encontrado o no pertenece al colegio o sede activa.");
        }

        $schedule->update(['status' => 'INACTIVE']);
        return $schedule;
    }
}
