<?php

namespace App\Domain\Grading\Services;

use App\Domain\Grading\Models\GradingScale;
use App\Domain\Grading\Models\GradingScaleAssignment;
use App\Domain\Grading\Models\GradingScaleItem;
use Illuminate\Support\Collection;

class GradeConversionService
{
    /**
     * Resolve the active grading scale using exact specificity score rules
     */
    public function resolveScale(
        int $schoolId,
        ?int $educationalLevelId = null,
        ?int $gradeId = null,
        ?int $courseId = null,
        ?int $subjectId = null,
        ?int $academicPeriodId = null
    ): GradingScale {
        // Query assignments for this school matching scope
        $assignments = GradingScaleAssignment::query()
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($educationalLevelId) {
                $q->whereNull('educational_level_id')->orWhere('educational_level_id', $educationalLevelId);
            })
            ->where(function ($q) use ($gradeId) {
                $q->whereNull('grade_id')->orWhere('grade_id', $gradeId);
            })
            ->where(function ($q) use ($courseId) {
                $q->whereNull('course_id')->orWhere('course_id', $courseId);
            })
            ->where(function ($q) use ($subjectId) {
                $q->whereNull('subject_id')->orWhere('subject_id', $subjectId);
            })
            ->where(function ($q) use ($academicPeriodId) {
                $q->whereNull('academic_period_id')->orWhere('academic_period_id', $academicPeriodId);
            })
            ->with('scale.items')
            ->get();

        if ($assignments->isNotEmpty()) {
            // Sort by priority_score descending
            $bestAssignment = $assignments->sortByDesc('priority_score')->first();
            return $bestAssignment->scale;
        }

        // Default scale fallback
        $defaultScale = GradingScale::query()
            ->where('school_id', $schoolId)
            ->where('is_default', true)
            ->with('items')
            ->first();

        if ($defaultScale) {
            return $defaultScale;
        }

        // Fallback to any active scale
        return GradingScale::query()
            ->where('school_id', $schoolId)
            ->with('items')
            ->firstOrFail();
    }

    /**
     * Convert an entered grade string into scale item & numeric equivalent
     */
    public function convertGrade(GradingScale $scale, string|float $enteredValue): array
    {
        $valueStr = (string) $enteredValue;
        $items = $scale->items;

        if ($scale->scale_type->value === 'NUMERIC') {
            $numVal = (float) $valueStr;
            $item = $items->first(function ($it) use ($numVal) {
                return $it->min_value !== null && $it->max_value !== null
                    && $numVal >= $it->min_value && $numVal <= $it->max_value;
            });

            return [
                'entered_value' => $valueStr,
                'normalized_value' => number_format($numVal, $scale->decimal_places, '.', ''),
                'equivalent_numeric_value' => $numVal,
                'grading_scale_item_id' => $item?->id,
                'display_value' => $item ? "{$numVal} ({$item->name})" : (string) $numVal,
            ];
        }

        // Qualitative or Letter scale
        $item = $items->first(function ($it) use ($valueStr) {
            return strcasecmp($it->name, $valueStr) === 0 || strcasecmp((string)$it->code, $valueStr) === 0;
        });

        return [
            'entered_value' => $valueStr,
            'normalized_value' => $item?->name ?? $valueStr,
            'equivalent_numeric_value' => $item?->equivalent_numeric_value,
            'grading_scale_item_id' => $item?->id,
            'display_value' => $item?->name ?? $valueStr,
        ];
    }
}
