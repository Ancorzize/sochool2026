<?php

namespace App\Domain\Academic\Services;

use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\CourseSubjectTeacher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CourseService
{
    public function listCourses(int $schoolId, ?int $campusId = null, ?int $academicYearId = null)
    {
        $query = Course::with(['campus', 'academicYear', 'grade', 'directorTeacher', 'subjectTeachers.teacher', 'subjectTeachers.subject'])
            ->where('school_id', $schoolId);

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->orderBy('name')->get();
    }

    public function createCourse(int $schoolId, array $data): Course
    {
        $data['school_id'] = $schoolId;

        // 1. Verify campus belongs to active tenant school
        $campus = DB::table('campuses')
            ->where('id', $data['campus_id'])
            ->where('school_id', $schoolId)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$campus) {
            throw new InvalidArgumentException("Sede no válida o no pertenece al colegio activo.");
        }

        // 2. Verify academic year belongs to active tenant school
        $academicYear = DB::table('academic_years')
            ->where('id', $data['academic_year_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (!$academicYear) {
            throw new InvalidArgumentException("Año lectivo no válido o no pertenece al colegio activo.");
        }

        // 3. Verify grade belongs to active tenant school
        $grade = DB::table('grades')
            ->where('id', $data['grade_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (!$grade) {
            throw new InvalidArgumentException("Grado no válido o no pertenece al colegio activo.");
        }

        // 4. Verify optional director teacher belongs to active tenant school
        if (!empty($data['director_teacher_id'])) {
            $teacher = DB::table('teachers')
                ->where('id', $data['director_teacher_id'])
                ->where('school_id', $schoolId)
                ->first();

            if (!$teacher) {
                throw new InvalidArgumentException("Docente director no válido o no pertenece al colegio activo.");
            }
        }

        // 5. Verify course name uniqueness per (school_id, campus_id, academic_year_id, name)
        $existing = DB::table('courses')
            ->where('school_id', $schoolId)
            ->where('campus_id', $data['campus_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('name', $data['name'])
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException("Ya existe un curso con este nombre en la sede y año lectivo especificados.");
        }

        $course = Course::create($data);
        return $course->load(['campus', 'academicYear', 'grade', 'directorTeacher']);
    }

    public function assignTeacher(int $schoolId, int $courseId, array $data): CourseSubjectTeacher
    {
        // 1. Verify course belongs to active tenant school
        $course = Course::where('id', $courseId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$course) {
            throw new InvalidArgumentException("Curso no encontrado o no pertenece al colegio activo.");
        }

        // 2. Verify subject belongs to active tenant school
        $subject = DB::table('subjects')
            ->where('id', $data['subject_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (!$subject) {
            throw new InvalidArgumentException("Asignatura no válida o no pertenece al colegio activo.");
        }

        // 3. Verify teacher belongs to active tenant school
        $teacher = DB::table('teachers')
            ->where('id', $data['teacher_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (!$teacher) {
            throw new InvalidArgumentException("Docente no válido o no pertenece al colegio activo.");
        }

        // 4. Create or update course subject teacher assignment
        $assignment = CourseSubjectTeacher::updateOrCreate(
            [
                'school_id' => $schoolId,
                'course_id' => $course->id,
                'subject_id' => $data['subject_id'],
            ],
            [
                'campus_id' => $course->campus_id,
                'teacher_id' => $data['teacher_id'],
                'hours_per_week' => $data['hours_per_week'] ?? 2,
            ]
        );

        return $assignment->load(['course', 'subject', 'teacher']);
    }
}
