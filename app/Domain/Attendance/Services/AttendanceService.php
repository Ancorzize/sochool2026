<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Models\Attendance;
use App\Infrastructure\Tenant\CampusContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttendanceService
{
    public function listAttendance(int $schoolId, array $filters = [])
    {
        $query = Attendance::with(['course', 'subject', 'student', 'status', 'recordedBy'])
            ->where('school_id', $schoolId);

        if (!empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['attendance_status_id'])) {
            $query->where('attendance_status_id', $filters['attendance_status_id']);
        }

        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('date', '<=', $filters['to_date']);
        }

        $activeCampusId = CampusContext::id();
        if ($activeCampusId) {
            $query->whereHas('course', function ($q) use ($activeCampusId) {
                $q->where('campus_id', $activeCampusId);
            });
        }

        return $query->orderBy('date', 'desc')->orderBy('id', 'asc')->get();
    }

    public function recordBatch(int $schoolId, array $data, int $userId)
    {
        $courseId = (int) $data['course_id'];
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $date = $data['date'];
        $records = $data['records'];

        // 1. Verify course belongs to active tenant school
        $course = DB::table('courses')
            ->where('id', $courseId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$course) {
            throw new InvalidArgumentException("Curso no encontrado o no pertenece al colegio activo.");
        }

        // 2. If campus context active, verify course belongs to active campus
        $activeCampusId = CampusContext::id();
        if ($activeCampusId && (int) $course->campus_id !== $activeCampusId) {
            throw new InvalidArgumentException("El curso no pertenece a la sede activa.");
        }

        // 3. Verify subject if provided
        if ($subjectId) {
            $subject = DB::table('subjects')
                ->where('id', $subjectId)
                ->where('school_id', $schoolId)
                ->first();

            if (!$subject) {
                throw new InvalidArgumentException("Asignatura no encontrada o no pertenece al colegio activo.");
            }
        }

        // 4. Validate all students belong to active tenant school AND have an active enrollment in this course
        $inputStudentIds = array_unique(array_map(fn($r) => (int) $r['student_id'], $records));

        $validStudentsCount = DB::table('students')
            ->whereIn('id', $inputStudentIds)
            ->where('school_id', $schoolId)
            ->count();

        if ($validStudentsCount !== count($inputStudentIds)) {
            throw new InvalidArgumentException("Uno o más estudiantes no son válidos o no pertenecen al colegio activo.");
        }

        // Check active enrollment in course
        $enrolledStudentIds = DB::table('course_enrollments')
            ->join('student_enrollments', 'student_enrollments.id', '=', 'course_enrollments.student_enrollment_id')
            ->where('course_enrollments.school_id', $schoolId)
            ->where('course_enrollments.course_id', $courseId)
            ->where('course_enrollments.status', 'ACTIVE')
            ->where('student_enrollments.school_id', $schoolId)
            ->where('student_enrollments.status', 'ACTIVE')
            ->whereIn('student_enrollments.student_id', $inputStudentIds)
            ->pluck('student_enrollments.student_id')
            ->toArray();

        foreach ($inputStudentIds as $sId) {
            if (!in_array($sId, $enrolledStudentIds)) {
                throw new InvalidArgumentException("El estudiante ID {$sId} no posee una matrícula activa en este curso.");
            }
        }

        // 5. Validate all attendance statuses belong to active tenant school
        $inputStatusIds = array_unique(array_map(fn($r) => (int) $r['attendance_status_id'], $records));

        $validStatusesCount = DB::table('attendance_statuses')
            ->whereIn('id', $inputStatusIds)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->count();

        if ($validStatusesCount !== count($inputStatusIds)) {
            throw new InvalidArgumentException("Uno o más estados de asistencia no son válidos o no pertenecen al colegio activo.");
        }

        // 6. Atomic Transaction for batch insertion/update
        return DB::transaction(function () use ($schoolId, $courseId, $subjectId, $date, $records, $userId) {
            $processed = [];

            foreach ($records as $item) {
                $studentId = (int) $item['student_id'];
                $statusId = (int) $item['attendance_status_id'];
                $isJustified = $item['is_justified'] ?? false;
                $remarks = $item['remarks'] ?? null;

                $attendance = Attendance::updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'course_id' => $courseId,
                        'student_id' => $studentId,
                        'date' => $date,
                        'subject_id' => $subjectId,
                    ],
                    [
                        'attendance_status_id' => $statusId,
                        'is_justified' => $isJustified,
                        'remarks' => $remarks,
                        'recorded_by_user_id' => $userId,
                    ]
                );

                $processed[] = $attendance->load(['course', 'subject', 'student', 'status', 'recordedBy']);
            }

            return $processed;
        });
    }

    public function updateAttendance(int $schoolId, int $id, array $data, int $userId): Attendance
    {
        // 1. Verify attendance record exists and belongs to active tenant school
        $attendance = Attendance::where('id', $id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$attendance) {
            throw new InvalidArgumentException("Registro de asistencia no encontrado o no pertenece al colegio activo.");
        }

        // 2. If campus context active, verify course belongs to active campus
        $activeCampusId = CampusContext::id();
        if ($activeCampusId) {
            $courseCampusId = DB::table('courses')
                ->where('id', $attendance->course_id)
                ->where('school_id', $schoolId)
                ->value('campus_id');

            if ((int) $courseCampusId !== $activeCampusId) {
                throw new InvalidArgumentException("El registro de asistencia no pertenece a la sede activa.");
            }
        }

        // 3. If attendance_status_id is provided, verify it belongs to active tenant school and is active
        if (array_key_exists('attendance_status_id', $data) && $data['attendance_status_id'] !== null) {
            $status = DB::table('attendance_statuses')
                ->where('id', (int) $data['attendance_status_id'])
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->first();

            if (!$status) {
                throw new InvalidArgumentException("El estado de asistencia especificado no es válido o no pertenece al colegio activo.");
            }
        }

        // 4. Prepare update payload exclusively for authorized fields
        $updateData = [];

        if (array_key_exists('attendance_status_id', $data)) {
            $updateData['attendance_status_id'] = (int) $data['attendance_status_id'];
        }

        if (array_key_exists('is_justified', $data)) {
            $updateData['is_justified'] = (bool) $data['is_justified'];
        }

        if (array_key_exists('remarks', $data)) {
            $updateData['remarks'] = $data['remarks'];
        }

        $updateData['recorded_by_user_id'] = $userId;

        $attendance->update($updateData);

        return $attendance->load(['course', 'subject', 'student', 'status', 'recordedBy']);
    }

    public function getStudentAttendance(int $schoolId, int $studentId, array $filters = [])
    {
        $student = DB::table('students')
            ->where('id', $studentId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            throw new InvalidArgumentException("Estudiante no encontrado o no pertenece al colegio activo.");
        }

        $filters['student_id'] = $studentId;

        return $this->listAttendance($schoolId, $filters);
    }
}
