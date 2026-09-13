<?php

namespace App\Domain\Grading\Services;

use App\Domain\Grading\Models\Assessment;
use App\Domain\Grading\Models\GradingScale;
use App\Domain\Grading\Models\PeriodFinalGrade;
use App\Domain\Grading\Models\PeriodGradeSnapshot;
use App\Domain\Grading\Models\StudentGrade;
use Illuminate\Support\Collection;

class GradeCalculationEngine
{
    public function __construct(
        protected GradeConversionService $conversionService
    ) {}

    /**
     * Calculate final grade for a student in a specific course, subject, and academic period
     */
    public function calculateSubjectPeriodGrade(
        int $schoolId,
        int $courseId,
        int $subjectId,
        int $studentId,
        int $academicPeriodId
    ): PeriodFinalGrade {
        $scale = $this->conversionService->resolveScale(
            $schoolId,
            courseId: $courseId,
            subjectId: $subjectId,
            academicPeriodId: $academicPeriodId
        );

        $assessments = Assessment::query()
            ->where('school_id', $schoolId)
            ->where('course_id', $courseId)
            ->where('subject_id', $subjectId)
            ->where('academic_period_id', $academicPeriodId)
            ->get();

        $grades = StudentGrade::query()
            ->where('school_id', $schoolId)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->where('student_id', $studentId)
            ->get();

        if ($scale->scale_type->value === 'NUMERIC') {
            $totalWeightedScore = 0.0;
            $totalWeightApplied = 0.0;

            foreach ($assessments as $assessment) {
                if ($assessment->weight_percentage <= 0) {
                    continue;
                }

                $grade = $grades->firstWhere('assessment_id', $assessment->id);

                if ($grade && !$grade->is_exempt && $grade->equivalent_numeric_value !== null) {
                    $totalWeightedScore += ($grade->equivalent_numeric_value * ($assessment->weight_percentage / 100));
                    $totalWeightApplied += $assessment->weight_percentage;
                }
            }

            $numericFinal = $totalWeightApplied > 0
                ? round($totalWeightedScore / ($totalWeightApplied / 100), $scale->decimal_places)
                : 0.0;

            $converted = $this->conversionService->convertGrade($scale, $numericFinal);

            return PeriodFinalGrade::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'course_id' => $courseId,
                    'subject_id' => $subjectId,
                    'student_id' => $studentId,
                    'academic_period_id' => $academicPeriodId,
                ],
                [
                    'final_entered_value' => (string) $numericFinal,
                    'numeric_score' => $numericFinal,
                    'grading_scale_item_id' => $converted['grading_scale_item_id'],
                    'status' => 'FINAL',
                ]
            );
        }

        // Qualitative scale calculation
        $latestGrade = $grades->last();
        $finalLabel = $latestGrade ? $latestGrade->entered_value : 'Pendiente';
        $converted = $this->conversionService->convertGrade($scale, $finalLabel);

        return PeriodFinalGrade::updateOrCreate(
            [
                'school_id' => $schoolId,
                'course_id' => $courseId,
                'subject_id' => $subjectId,
                'student_id' => $studentId,
                'academic_period_id' => $academicPeriodId,
            ],
            [
                'final_entered_value' => $finalLabel,
                'numeric_score' => $converted['equivalent_numeric_value'],
                'grading_scale_item_id' => $converted['grading_scale_item_id'],
                'status' => 'FINAL',
            ]
        );
    }

    /**
     * Create an immutable period grade snapshot JSONB for a student
     */
    public function createPeriodGradeSnapshot(
        int $schoolId,
        int $courseId,
        int $studentId,
        int $academicPeriodId
    ): PeriodGradeSnapshot {
        $finalGrades = PeriodFinalGrade::query()
            ->where('school_id', $schoolId)
            ->where('course_id', $courseId)
            ->where('student_id', $studentId)
            ->where('academic_period_id', $academicPeriodId)
            ->with(['subject', 'scaleItem'])
            ->get();

        $snapshotData = [
            'calculation_version' => '1.0',
            'calculated_at' => now()->toDateTimeString(),
            'subjects' => $finalGrades->map(fn($g) => [
                'subject_id' => $g->subject_id,
                'subject_name' => $g->subject?->name,
                'final_entered_value' => $g->final_entered_value,
                'numeric_score' => $g->numeric_score,
                'scale_item_name' => $g->scaleItem?->name,
                'is_recovery' => $g->is_recovery,
            ])->toArray(),
        ];

        return PeriodGradeSnapshot::updateOrCreate(
            [
                'school_id' => $schoolId,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'academic_period_id' => $academicPeriodId,
            ],
            [
                'calculation_version' => '1.0',
                'snapshot_data' => $snapshotData,
                'calculated_at' => now(),
            ]
        );
    }
}
