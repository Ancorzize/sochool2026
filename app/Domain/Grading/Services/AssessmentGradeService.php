<?php

namespace App\Domain\Grading\Services;

use App\Domain\Grading\Models\Assessment;
use App\Domain\Grading\Models\StudentGrade;
use App\Infrastructure\Tenant\CampusContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssessmentGradeService
{
    public function __construct(
        protected GradeConversionService $conversionService,
        protected GradeCalculationEngine $gradeCalculationEngine
    ) {}

    /**
     * Record or update grades for an assessment in bulk.
     *
     * @param int $schoolId
     * @param int $assessmentId
     * @param array $gradesPayload
     * @param int|null $userId
     * @return array
     * @throws InvalidArgumentException
     */
    public function recordAssessmentGrades(int $schoolId, int $assessmentId, array $gradesPayload, ?int $userId = null): array
    {
        // 1. Verify Assessment exists and belongs to schoolId
        $assessment = Assessment::with(['course', 'subject', 'academicPeriod'])
            ->where('id', $assessmentId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$assessment) {
            throw new InvalidArgumentException("La evaluación no existe o no pertenece al colegio activo.");
        }

        // 2. Check CampusContext
        $activeCampusId = CampusContext::id();
        if ($activeCampusId && (int) $assessment->course->campus_id !== $activeCampusId) {
            throw new InvalidArgumentException("La evaluación no pertenece a la sede activa.");
        }

        // 3. Check Academic Period Status
        if ($assessment->academicPeriod?->isLockedOrClosed()) {
            throw new InvalidArgumentException("No se pueden registrar calificaciones en un período académico cerrado o bloqueado.");
        }

        // 4. Validate duplicate student_id in request payload
        $inputStudentIds = array_map(fn($g) => (int) $g['student_id'], $gradesPayload);
        if (count($inputStudentIds) !== count(array_unique($inputStudentIds))) {
            throw new InvalidArgumentException("El request contiene registros duplicados para el mismo estudiante.");
        }

        // 5. Validate student existence in tenant school
        $validStudentsCount = DB::table('students')
            ->whereIn('id', $inputStudentIds)
            ->where('school_id', $schoolId)
            ->count();

        if ($validStudentsCount !== count($inputStudentIds)) {
            throw new InvalidArgumentException("Uno o más estudiantes no existen o no pertenecen al colegio activo.");
        }

        // Validate active course enrollment
        $enrolledStudentIds = DB::table('course_enrollments')
            ->join('student_enrollments', 'student_enrollments.id', '=', 'course_enrollments.student_enrollment_id')
            ->where('course_enrollments.school_id', $schoolId)
            ->where('course_enrollments.course_id', $assessment->course_id)
            ->where('course_enrollments.status', 'ACTIVE')
            ->where('student_enrollments.school_id', $schoolId)
            ->whereIn('student_enrollments.status', ['ACTIVE', 'ENROLLED'])
            ->whereIn('student_enrollments.student_id', $inputStudentIds)
            ->pluck('student_enrollments.student_id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        foreach ($inputStudentIds as $sId) {
            if (!in_array($sId, $enrolledStudentIds, true)) {
                throw new InvalidArgumentException("El estudiante ID {$sId} no está matriculado activamente en el curso de la evaluación.");
            }
        }

        // 6. Resolve Grading Scale for assessment's context
        $scale = $this->conversionService->resolveScale(
            $schoolId,
            courseId: $assessment->course_id,
            subjectId: $assessment->subject_id,
            academicPeriodId: $assessment->academic_period_id
        );

        // 7. Validate each entered_value against scale & max_score
        foreach ($gradesPayload as $item) {
            $enteredVal = $item['entered_value'];
            $isExempt = !empty($item['is_exempt']);

            if (!$isExempt) {
                if ($scale->scale_type->value === 'NUMERIC') {
                    if (!is_numeric($enteredVal)) {
                        throw new InvalidArgumentException("La calificación '{$enteredVal}' debe ser numérica.");
                    }
                    $numVal = (float) $enteredVal;
                    $minScore = $scale->min_score ?? 0.0;
                    $maxScore = $assessment->max_score ?? $scale->max_score ?? 5.0;

                    if ($numVal < $minScore || $numVal > $maxScore) {
                        throw new InvalidArgumentException("La calificación {$numVal} está fuera del rango permitido ({$minScore} - {$maxScore}).");
                    }
                } else {
                    // Qualitative scale item match
                    $valStr = (string) $enteredVal;
                    $matchedItem = $scale->items->first(function ($it) use ($valStr) {
                        return strcasecmp($it->name, $valStr) === 0 || strcasecmp((string)$it->code, $valStr) === 0;
                    });
                    if (!$matchedItem) {
                        throw new InvalidArgumentException("La calificación '{$valStr}' no corresponde a una escala cualitativa válida.");
                    }
                }
            }
        }

        // 8. Atomic Transaction
        return DB::transaction(function () use ($schoolId, $assessment, $gradesPayload, $scale, $userId) {
            $savedGrades = [];

            foreach ($gradesPayload as $item) {
                $studentId = (int) $item['student_id'];
                $enteredVal = $item['entered_value'];
                $isExempt = !empty($item['is_exempt']);
                $comments = $item['comments'] ?? null;

                $converted = $this->conversionService->convertGrade($scale, $enteredVal);

                $existingGrade = StudentGrade::where('school_id', $schoolId)
                    ->where('assessment_id', $assessment->id)
                    ->where('student_id', $studentId)
                    ->first();

                $createdUserId = $existingGrade ? $existingGrade->created_by_user_id : $userId;

                $gradeRecord = StudentGrade::updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'assessment_id' => $assessment->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'entered_value' => (string) $enteredVal,
                        'normalized_value' => $converted['normalized_value'],
                        'equivalent_numeric_value' => $converted['equivalent_numeric_value'],
                        'grading_scale_item_id' => $converted['grading_scale_item_id'],
                        'display_value' => $converted['display_value'],
                        'is_exempt' => $isExempt,
                        'comments' => $comments,
                        'created_by_user_id' => $createdUserId,
                        'updated_by_user_id' => $userId,
                    ]
                );

                $savedGrades[] = $gradeRecord->load(['student', 'scaleItem']);

                // Calculate Period Final Grade for each student using domain calculation engine
                $this->gradeCalculationEngine->calculateSubjectPeriodGrade(
                    $schoolId,
                    $assessment->course_id,
                    $assessment->subject_id,
                    $studentId,
                    $assessment->academic_period_id
                );
            }

            return $savedGrades;
        });
    }
}
