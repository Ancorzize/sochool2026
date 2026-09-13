<?php

namespace App\Domain\Grading\Services;

use App\Domain\Grading\Models\Assessment;
use App\Infrastructure\Tenant\CampusContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssessmentService
{
    /**
     * Create assessments for single or multiple courses (atomic mass assignment).
     *
     * @param int $schoolId
     * @param array $data
     * @param int|null $userId
     * @return array
     * @throws InvalidArgumentException
     */
    public function createAssessments(int $schoolId, array $data, ?int $userId = null): array
    {
        $subjectId = (int) $data['subject_id'];
        $academicPeriodId = (int) $data['academic_period_id'];
        $title = $data['title'];
        $description = $data['description'] ?? null;
        $weightPercentage = (float) $data['weight_percentage'];
        $dueDate = $data['due_date'] ?? null;
        $maxScore = isset($data['max_score']) ? (float) $data['max_score'] : 5.00;
        $assessmentCategoryId = isset($data['assessment_category_id']) ? (int) $data['assessment_category_id'] : null;

        // Resolve course IDs array from input
        $courseIds = [];
        if (isset($data['course_ids']) && is_array($data['course_ids'])) {
            $courseIds = array_map('intval', array_unique($data['course_ids']));
        } elseif (isset($data['course_id'])) {
            $courseIds = [(int) $data['course_id']];
        }

        if (empty($courseIds)) {
            throw new InvalidArgumentException("Debe especificar al menos un curso válido (course_id o course_ids).");
        }

        // 1. Verify Academic Period belongs to tenant school and is OPEN
        $period = DB::table('academic_periods')
            ->where('id', $academicPeriodId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$period) {
            throw new InvalidArgumentException("El período académico no existe o no pertenece al colegio activo.");
        }

        if (in_array($period->status, ['CLOSED', 'LOCKED'], true)) {
            throw new InvalidArgumentException("No se pueden crear evaluaciones en un período académico cerrado o bloqueado.");
        }

        // 2. Verify Subject belongs to tenant school
        $subject = DB::table('subjects')
            ->where('id', $subjectId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$subject) {
            throw new InvalidArgumentException("La asignatura no existe o no pertenece al colegio activo.");
        }

        // 3. Verify Assessment Category if provided
        if ($assessmentCategoryId !== null) {
            $category = DB::table('assessment_categories')
                ->where('id', $assessmentCategoryId)
                ->where('school_id', $schoolId)
                ->first();

            if (!$category) {
                throw new InvalidArgumentException("La categoría de evaluación no existe o no pertenece al colegio activo.");
            }
        }

        // 4. Resolve Teacher if provided, or fallback via user/course assignment/school
        $explicitTeacherId = isset($data['teacher_id']) ? (int) $data['teacher_id'] : null;
        if ($explicitTeacherId !== null) {
            $teacher = DB::table('teachers')
                ->where('id', $explicitTeacherId)
                ->where('school_id', $schoolId)
                ->first();

            if (!$teacher) {
                throw new InvalidArgumentException("El docente especificado no existe o no pertenece al colegio activo.");
            }
        } else if ($userId) {
            $explicitTeacherId = DB::table('teachers')
                ->where('user_id', $userId)
                ->where('school_id', $schoolId)
                ->value('id');
        }

        // 5. Verify all target courses belong to tenant school & active CampusContext (if set)
        $activeCampusId = CampusContext::id();

        foreach ($courseIds as $cId) {
            $course = DB::table('courses')
                ->where('id', $cId)
                ->where('school_id', $schoolId)
                ->first();

            if (!$course) {
                throw new InvalidArgumentException("El curso con ID {$cId} no existe o no pertenece al colegio activo.");
            }

            if ($activeCampusId && (int) $course->campus_id !== $activeCampusId) {
                throw new InvalidArgumentException("El curso con ID {$cId} no pertenece a la sede activa.");
            }

            // 6. Weight accumulation check per course
            $existingWeight = (float) DB::table('assessments')
                ->where('school_id', $schoolId)
                ->where('course_id', $cId)
                ->where('subject_id', $subjectId)
                ->where('academic_period_id', $academicPeriodId)
                ->sum('weight_percentage');

            $totalWeight = round($existingWeight + $weightPercentage, 2);

            if ($totalWeight > 100.00) {
                throw new InvalidArgumentException("La suma total de pesos de las evaluaciones para el curso ID {$cId} supera el 100% (actual: {$existingWeight}%, nuevo: {$weightPercentage}%).");
            }
        }

        // 7. Atomic Transaction: create independent assessment row for each course
        return DB::transaction(function () use (
            $schoolId,
            $courseIds,
            $subjectId,
            $academicPeriodId,
            $assessmentCategoryId,
            $explicitTeacherId,
            $title,
            $description,
            $weightPercentage,
            $dueDate,
            $maxScore
        ) {
            $created = [];

            foreach ($courseIds as $cId) {
                $teacherId = $explicitTeacherId;

                if ($teacherId === null) {
                    $teacherId = DB::table('course_subject_teachers')
                        ->where('school_id', $schoolId)
                        ->where('course_id', $cId)
                        ->where('subject_id', $subjectId)
                        ->value('teacher_id');
                }

                if ($teacherId === null) {
                    $teacherId = DB::table('teachers')
                        ->where('school_id', $schoolId)
                        ->value('id');
                }

                if ($teacherId === null) {
                    throw new InvalidArgumentException("Debe especificar 'teacher_id' o asignar un docente a la asignatura del curso ID {$cId}.");
                }

                $assessment = Assessment::create([
                    'school_id' => $schoolId,
                    'course_id' => $cId,
                    'subject_id' => $subjectId,
                    'academic_period_id' => $academicPeriodId,
                    'assessment_category_id' => $assessmentCategoryId,
                    'teacher_id' => $teacherId,
                    'title' => $title,
                    'description' => $description,
                    'weight_percentage' => $weightPercentage,
                    'due_date' => $dueDate,
                    'max_score' => $maxScore,
                ]);

                $created[] = $assessment->load(['course', 'subject', 'academicPeriod', 'category', 'teacher']);
            }

            return $created;
        });
    }

    /**
     * List assessments for a specific course and subject.
     *
     * @param int $schoolId
     * @param int $courseId
     * @param int $subjectId
     * @param array $filters
     * @return array
     * @throws InvalidArgumentException
     */
    public function listCourseSubjectAssessments(int $schoolId, int $courseId, int $subjectId, array $filters = []): array
    {
        // 1. Verify course belongs to tenant and active campus
        $course = DB::table('courses')
            ->where('id', $courseId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$course) {
            throw new InvalidArgumentException("El curso no existe o no pertenece al colegio activo.");
        }

        $activeCampusId = CampusContext::id();
        if ($activeCampusId && (int) $course->campus_id !== $activeCampusId) {
            throw new InvalidArgumentException("El curso no pertenece a la sede activa.");
        }

        // 2. Verify subject belongs to tenant
        $subject = DB::table('subjects')
            ->where('id', $subjectId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$subject) {
            throw new InvalidArgumentException("La asignatura no existe o no pertenece al colegio activo.");
        }

        // 3. Query assessments
        $query = Assessment::with(['category', 'teacher', 'academicPeriod'])
            ->where('school_id', $schoolId)
            ->where('course_id', $courseId)
            ->where('subject_id', $subjectId);

        if (!empty($filters['academic_period_id'])) {
            $query->where('academic_period_id', (int) $filters['academic_period_id']);
        }

        return $query->orderBy('due_date', 'asc')->orderBy('id', 'asc')->get()->all();
    }
}
