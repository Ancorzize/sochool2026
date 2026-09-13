<?php

namespace App\Domain\ReportCard\Services;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Course;
use App\Domain\Attendance\Models\Attendance;
use App\Domain\Grading\Models\PeriodFinalGrade;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Models\ReportCardAssignment;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\ReportCard\Models\TeacherPeriodObservation;
use App\Domain\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportCardEngine
{
    /**
     * Resolve report card template by precedence:
     * 1. Course + Subject
     * 2. Course
     * 3. Grade + Subject
     * 4. Grade
     * 5. Educational Level
     * 6. Default active template
     */
    public function resolveTemplate(int $schoolId, Course $course, ?int $subjectId = null): ReportCardTemplate
    {
        // 1. Course + Subject level assignment
        if ($subjectId) {
            $assignment = ReportCardAssignment::query()
                ->where('school_id', $schoolId)
                ->where('course_id', $course->id)
                ->where('subject_id', $subjectId)
                ->with('template')
                ->first();

            if ($assignment && $assignment->template) {
                return $assignment->template;
            }
        }

        // 2. Course level assignment (subject_id is null)
        $assignment = ReportCardAssignment::query()
            ->where('school_id', $schoolId)
            ->where('course_id', $course->id)
            ->whereNull('subject_id')
            ->with('template')
            ->first();

        if ($assignment && $assignment->template) {
            return $assignment->template;
        }

        // 3. Grade + Subject level assignment
        if ($subjectId && $course->grade_id) {
            $assignment = ReportCardAssignment::query()
                ->where('school_id', $schoolId)
                ->where('grade_id', $course->grade_id)
                ->where('subject_id', $subjectId)
                ->whereNull('course_id')
                ->with('template')
                ->first();

            if ($assignment && $assignment->template) {
                return $assignment->template;
            }
        }

        // 4. Grade level assignment (subject_id is null, course_id is null)
        if ($course->grade_id) {
            $assignment = ReportCardAssignment::query()
                ->where('school_id', $schoolId)
                ->where('grade_id', $course->grade_id)
                ->whereNull('subject_id')
                ->whereNull('course_id')
                ->with('template')
                ->first();

            if ($assignment && $assignment->template) {
                return $assignment->template;
            }
        }

        // 5. Educational Level assignment
        $levelId = $course->grade?->educational_level_id;
        if ($levelId) {
            $assignment = ReportCardAssignment::query()
                ->where('school_id', $schoolId)
                ->where('educational_level_id', $levelId)
                ->whereNull('grade_id')
                ->whereNull('course_id')
                ->whereNull('subject_id')
                ->with('template')
                ->first();

            if ($assignment && $assignment->template) {
                return $assignment->template;
            }
        }

        // 6. Default active template
        return ReportCardTemplate::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Generate or regenerate a versioned report card with historic snapshot
     */
    public function generateReportCard(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        int $academicPeriodId,
        Course $course,
        ?string $regenerationReason = null
    ): ReportCard {
        // Validate entity ownership within tenant
        $student = Student::query()->where('school_id', $schoolId)->findOrFail($studentId);
        $year = AcademicYear::query()->where('school_id', $schoolId)->findOrFail($academicYearId);
        $period = AcademicPeriod::query()->where('school_id', $schoolId)->where('academic_year_id', $year->id)->findOrFail($academicPeriodId);

        if ($course->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'course' => ['El curso no pertenece al colegio activo.'],
            ]);
        }

        return DB::transaction(function () use ($schoolId, $student, $year, $period, $course, $regenerationReason) {
            // Check for existing latest version
            $existingLatest = ReportCard::query()
                ->where('school_id', $schoolId)
                ->where('student_id', $student->id)
                ->where('academic_year_id', $year->id)
                ->where('academic_period_id', $period->id)
                ->where('is_latest', true)
                ->first();

            if ($existingLatest && in_array($existingLatest->status, ['LOCKED', 'PUBLISHED'], true)) {
                throw ValidationException::withMessages([
                    'report_card' => ["El boletín de este estudiante se encuentra en estado {$existingLatest->status} y no puede ser regenerado."],
                ]);
            }

            $template = $this->resolveTemplate($schoolId, $course);
            $template->loadMissing(['sections.fields']);

            // Fetch final grades
            $finalGrades = PeriodFinalGrade::query()
                ->where('school_id', $schoolId)
                ->where('course_id', $course->id)
                ->where('student_id', $student->id)
                ->where('academic_period_id', $period->id)
                ->with(['subject', 'scaleItem.scale'])
                ->get();

            // Fetch observations
            $observations = TeacherPeriodObservation::query()
                ->where('school_id', $schoolId)
                ->where('course_id', $course->id)
                ->where('student_id', $student->id)
                ->where('academic_period_id', $period->id)
                ->get();

            // Fetch attendance summary
            $attendances = Attendance::query()
                ->where('school_id', $schoolId)
                ->where('course_id', $course->id)
                ->where('student_id', $student->id)
                ->with('status')
                ->get();

            $totalAttendanceRecords = $attendances->count();
            $absenceCount = $attendances->filter(fn($a) => $a->status?->is_absence)->count();
            $justifiedCount = $attendances->filter(fn($a) => $a->is_justified)->count();
            $presentCount = $totalAttendanceRecords - $absenceCount;

            $dataSnapshot = [
                'generated_at' => now()->toDateTimeString(),
                'school_id' => $schoolId,
                'academic_year' => [
                    'id' => $year->id,
                    'name' => $year->name,
                ],
                'academic_period' => [
                    'id' => $period->id,
                    'name' => $period->name,
                ],
                'student' => [
                    'id' => $student->id,
                    'name' => "{$student->first_name} {$student->last_name}",
                    'code' => $student->student_code,
                    'document' => "{$student->document_type} {$student->document_number}",
                ],
                'course' => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'grade' => $course->grade ? [
                        'id' => $course->grade->id,
                        'name' => $course->grade->name,
                    ] : null,
                    'educational_level' => $course->grade?->educationalLevel ? [
                        'id' => $course->grade->educationalLevel->id,
                        'name' => $course->grade->educationalLevel->name,
                    ] : null,
                ],
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'header_html' => $template->header_html,
                    'footer_html' => $template->footer_html,
                    'layout_config' => $template->layout_config,
                    'sections' => $template->sections->map(fn($sec) => [
                        'id' => $sec->id,
                        'type' => $sec->section_type,
                        'title' => $sec->title,
                        'order' => $sec->section_order,
                        'config' => $sec->config,
                        'fields' => $sec->fields->map(fn($f) => [
                            'key' => $f->field_key,
                            'label' => $f->label,
                            'is_visible' => $f->is_visible,
                            'order' => $f->field_order,
                        ])->toArray(),
                    ])->toArray(),
                ],
                'grades' => $finalGrades->map(fn($g) => [
                    'subject_id' => $g->subject_id,
                    'subject_name' => $g->subject?->name,
                    'subject_code' => $g->subject?->code,
                    'entered_value' => $g->final_entered_value,
                    'numeric_score' => $g->numeric_score,
                    'is_recovery' => $g->is_recovery,
                    'recovery_score' => $g->recovery_score,
                    'performance_item' => $g->scaleItem ? [
                        'id' => $g->scaleItem->id,
                        'name' => $g->scaleItem->name,
                        'label' => $g->scaleItem->label,
                        'description' => $g->scaleItem->description,
                        'color' => $g->scaleItem->color,
                        'equivalent_numeric_value' => $g->scaleItem->equivalent_numeric_value,
                        'scale_name' => $g->scaleItem->scale?->name,
                        'scale_type' => $g->scaleItem->scale?->scale_type,
                    ] : null,
                ])->toArray(),
                'attendance_summary' => [
                    'total_records' => $totalAttendanceRecords,
                    'present_count' => $presentCount,
                    'absent_count' => $absenceCount,
                    'justified_count' => $justifiedCount,
                ],
                'observations' => $observations->map(fn($o) => [
                    'subject_id' => $o->subject_id,
                    'teacher_id' => $o->teacher_id,
                    'observation' => $o->observation,
                ])->toArray(),
            ];

            $version = 1;
            $parentId = null;

            if ($existingLatest) {
                $version = $existingLatest->version + 1;
                $parentId = $existingLatest->id;

                // Mark previous version as superseded
                $existingLatest->update([
                    'is_latest' => false,
                    'status' => 'SUPERSEDED',
                ]);
            }

            return ReportCard::create([
                'school_id' => $schoolId,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'academic_period_id' => $period->id,
                'course_id' => $course->id,
                'template_id' => $template->id,
                'version' => $version,
                'is_latest' => true,
                'status' => 'GENERATED',
                'regeneration_reason' => $regenerationReason,
                'parent_report_card_id' => $parentId,
                'data_snapshot' => $dataSnapshot,
            ]);
        });
    }
}
