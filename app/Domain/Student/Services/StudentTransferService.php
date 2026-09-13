<?php

namespace App\Domain\Student\Services;

use App\Domain\Student\Models\StudentTransfer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentTransferService
{
    public function courseTransfer(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        int $fromCourseId,
        int $toCourseId,
        ?string $reason,
        int $userId
    ): StudentTransfer {
        return DB::transaction(function () use ($schoolId, $studentId, $academicYearId, $fromCourseId, $toCourseId, $reason, $userId) {
            // Find active course enrollment for fromCourseId
            $enrollment = DB::table('course_enrollments')
                ->where('school_id', $schoolId)
                ->where('course_id', $fromCourseId)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$enrollment) {
                throw new InvalidArgumentException("Inscripción de curso origen activa no encontrada.");
            }

            // Get campus_id of course
            $course = DB::table('courses')->where('id', $fromCourseId)->where('school_id', $schoolId)->firstOrFail();
            $targetCourse = DB::table('courses')->where('id', $toCourseId)->where('school_id', $schoolId)->firstOrFail();

            if ($course->campus_id !== $targetCourse->campus_id) {
                throw new InvalidArgumentException("Un traslado de curso requiere que ambos cursos pertenezcan a la misma sede.");
            }

            // Close old enrollment
            DB::table('course_enrollments')
                ->where('id', $enrollment->id)
                ->update(['status' => 'TRANSFERRED', 'updated_at' => now()]);

            // Create new course enrollment
            DB::table('course_enrollments')->insert([
                'school_id' => $schoolId,
                'campus_id' => $course->campus_id,
                'student_enrollment_id' => $enrollment->student_enrollment_id,
                'course_id' => $toCourseId,
                'enrolled_at' => now(),
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log transfer
            return StudentTransfer::create([
                'school_id' => $schoolId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'transfer_type' => 'COURSE_TRANSFER',
                'from_campus_id' => $course->campus_id,
                'to_campus_id' => $course->campus_id,
                'from_course_id' => $fromCourseId,
                'to_course_id' => $toCourseId,
                'effective_date' => now()->toDateString(),
                'reason' => $reason,
                'created_by_user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    public function campusTransfer(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        int $fromCampusId,
        int $toCampusId,
        int $toCourseId,
        ?string $reason,
        int $userId
    ): StudentTransfer {
        return DB::transaction(function () use ($schoolId, $studentId, $academicYearId, $fromCampusId, $toCampusId, $toCourseId, $reason, $userId) {
            // Find active student enrollment
            $se = DB::table('student_enrollments')
                ->where('school_id', $schoolId)
                ->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)
                ->where('campus_id', $fromCampusId)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$se) {
                throw new InvalidArgumentException("Matrícula de sede origen no encontrada.");
            }

            // Close current student enrollment & course enrollments
            DB::table('student_enrollments')
                ->where('id', $se->id)
                ->update(['status' => 'TRANSFERRED', 'updated_at' => now()]);

            DB::table('course_enrollments')
                ->where('student_enrollment_id', $se->id)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'TRANSFERRED', 'updated_at' => now()]);

            // Create new student enrollment for target campus
            $targetCourse = DB::table('courses')->where('id', $toCourseId)->where('school_id', $schoolId)->firstOrFail();

            $newSeId = DB::table('student_enrollments')->insertGetId([
                'school_id' => $schoolId,
                'campus_id' => $toCampusId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'grade_id' => $targetCourse->grade_id,
                'enrollment_date' => now()->toDateString(),
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('course_enrollments')->insert([
                'school_id' => $schoolId,
                'campus_id' => $toCampusId,
                'student_enrollment_id' => $newSeId,
                'course_id' => $toCourseId,
                'enrolled_at' => now(),
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return StudentTransfer::create([
                'school_id' => $schoolId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'transfer_type' => 'CAMPUS_TRANSFER',
                'from_campus_id' => $fromCampusId,
                'to_campus_id' => $toCampusId,
                'from_course_id' => null,
                'to_course_id' => $toCourseId,
                'effective_date' => now()->toDateString(),
                'reason' => $reason,
                'created_by_user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }
}
