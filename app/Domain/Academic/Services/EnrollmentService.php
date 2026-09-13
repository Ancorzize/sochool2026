<?php

namespace App\Domain\Academic\Services;

use App\Domain\Academic\Models\CourseEnrollment;
use App\Domain\Academic\Models\StudentEnrollment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnrollmentService
{
    public function createEnrollment(int $schoolId, array $data): StudentEnrollment
    {
        $data['school_id'] = $schoolId;

        // 1. Verify campus belongs to active tenant school and is ACTIVE
        $campus = DB::table('campuses')
            ->where('id', $data['campus_id'])
            ->where('school_id', $schoolId)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$campus) {
            throw new InvalidArgumentException("Sede no válida o no pertenece al colegio activo.");
        }

        // 2. Verify student belongs to active tenant school
        $student = DB::table('students')
            ->where('id', $data['student_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            throw new InvalidArgumentException("Estudiante no válido o no pertenece al colegio activo.");
        }

        // 3. Verify academic year belongs to active tenant school
        $academicYear = DB::table('academic_years')
            ->where('id', $data['academic_year_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (!$academicYear) {
            throw new InvalidArgumentException("Año lectivo no válido o no pertenece al colegio activo.");
        }

        // 4. Verify grade belongs to active tenant school
        $grade = DB::table('grades')
            ->where('id', $data['grade_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (!$grade) {
            throw new InvalidArgumentException("Grado no válido o no pertenece al colegio activo.");
        }

        // 5. Verify course belongs to active tenant school, specified campus, and academic year
        $course = DB::table('courses')
            ->where('id', $data['course_id'])
            ->where('school_id', $schoolId)
            ->where('campus_id', $data['campus_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->first();

        if (!$course) {
            throw new InvalidArgumentException("Curso no válido, no pertenece a la sede o año lectivo especificado.");
        }

        // 6. Check single ACTIVE enrollment per student and academic year
        $existingActive = StudentEnrollment::where('school_id', $schoolId)
            ->where('student_id', $data['student_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('status', 'ACTIVE')
            ->exists();

        if ($existingActive) {
            throw new InvalidArgumentException("El estudiante ya posee una matrícula activa para este año lectivo.");
        }

        // 7. Atomic creation of student_enrollments & course_enrollments
        try {
            return DB::transaction(function () use ($schoolId, $data) {
                $studentEnrollment = StudentEnrollment::create([
                    'school_id' => $schoolId,
                    'campus_id' => $data['campus_id'],
                    'student_id' => $data['student_id'],
                    'academic_year_id' => $data['academic_year_id'],
                    'grade_id' => $data['grade_id'],
                    'enrollment_number' => $data['enrollment_number'] ?? null,
                    'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
                    'status' => 'ACTIVE',
                ]);

                CourseEnrollment::create([
                    'school_id' => $schoolId,
                    'campus_id' => $data['campus_id'],
                    'student_enrollment_id' => $studentEnrollment->id,
                    'course_id' => $data['course_id'],
                    'enrolled_at' => $data['enrolled_at'] ?? now(),
                    'status' => 'ACTIVE',
                ]);

                return $studentEnrollment->load(['campus', 'student', 'academicYear', 'grade', 'courseEnrollments.course']);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'unique_active_student_enrollment') || str_contains($e->getMessage(), '23505')) {
                throw new InvalidArgumentException("El estudiante ya posee una matrícula activa para este año lectivo.");
            }
            throw $e;
        }
    }
}
