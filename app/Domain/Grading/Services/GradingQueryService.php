<?php

namespace App\Domain\Grading\Services;

use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\CourseEnrollment;
use App\Domain\Academic\Models\Subject;
use App\Domain\Grading\Models\Assessment;
use App\Domain\Grading\Models\PeriodFinalGrade;
use App\Domain\Grading\Models\StudentGrade;
use App\Domain\Student\Models\Student;
use App\Infrastructure\Tenant\CampusContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GradingQueryService
{
    /**
     * Get the grades matrix for a course and subject.
     *
     * @param int $schoolId
     * @param int $courseId
     * @param int $subjectId
     * @param array $filters
     * @return array
     * @throws InvalidArgumentException
     */
    public function getGradesMatrix(int $schoolId, int $courseId, int $subjectId, array $filters = []): array
    {
        // 1. Verify Course exists and belongs to tenant school & active CampusContext
        $course = Course::with(['grade', 'campus', 'academicYear'])
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

        // 2. Verify Subject exists and belongs to tenant school
        $subject = Subject::where('id', $subjectId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$subject) {
            throw new InvalidArgumentException("La asignatura no existe o no pertenece al colegio activo.");
        }

        $academicPeriodId = !empty($filters['academic_period_id']) ? (int) $filters['academic_period_id'] : null;
        if ($academicPeriodId) {
            $periodExists = DB::table('academic_periods')
                ->where('id', $academicPeriodId)
                ->where('school_id', $schoolId)
                ->exists();

            if (!$periodExists) {
                throw new InvalidArgumentException("El período académico especificado no existe o no pertenece al colegio activo.");
            }
        }

        // 3. Fetch enrolled students for this course
        $students = Student::query()
            ->where('school_id', $schoolId)
            ->whereHas('enrollments', function ($query) use ($schoolId, $courseId) {
                $query->where('school_id', $schoolId)
                    ->whereIn('status', ['ACTIVE', 'ENROLLED'])
                    ->whereHas('courseEnrollments', function ($cq) use ($schoolId, $courseId) {
                        $cq->where('school_id', $schoolId)
                            ->where('course_id', $courseId)
                            ->where('status', 'ACTIVE');
                    });
            })
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();

        // 4. Fetch assessments for course & subject
        $assessmentsQuery = Assessment::with(['category', 'teacher', 'academicPeriod'])
            ->where('school_id', $schoolId)
            ->where('course_id', $courseId)
            ->where('subject_id', $subjectId);

        if ($academicPeriodId) {
            $assessmentsQuery->where('academic_period_id', $academicPeriodId);
        }

        $assessments = $assessmentsQuery->orderBy('due_date', 'asc')->orderBy('id', 'asc')->get();

        $assessmentIds = $assessments->pluck('id')->toArray();
        $studentIds = $students->pluck('id')->toArray();

        // 5. Fetch StudentGrades
        $studentGrades = StudentGrade::with(['scaleItem'])
            ->where('school_id', $schoolId)
            ->whereIn('assessment_id', $assessmentIds)
            ->whereIn('student_id', $studentIds)
            ->get();

        // Map grades by "student_id:assessment_id"
        $gradesMap = [];
        foreach ($studentGrades as $sg) {
            $key = "{$sg->student_id}:{$sg->assessment_id}";
            $gradesMap[$key] = [
                'assessment_id' => $sg->assessment_id,
                'entered_value' => $sg->entered_value,
                'normalized_value' => $sg->normalized_value,
                'equivalent_numeric_value' => $sg->equivalent_numeric_value !== null ? (float) $sg->equivalent_numeric_value : null,
                'display_value' => $sg->display_value,
                'is_exempt' => (bool) $sg->is_exempt,
                'comments' => $sg->comments,
                'scale_item_name' => $sg->scaleItem?->name,
            ];
        }

        // 6. Fetch PeriodFinalGrades
        $finalGradesQuery = PeriodFinalGrade::with(['scaleItem', 'academicPeriod'])
            ->where('school_id', $schoolId)
            ->where('course_id', $courseId)
            ->where('subject_id', $subjectId)
            ->whereIn('student_id', $studentIds);

        if ($academicPeriodId) {
            $finalGradesQuery->where('academic_period_id', $academicPeriodId);
        }

        $finalGrades = $finalGradesQuery->get();

        // Map final grades by "student_id:academic_period_id"
        $finalGradesMap = [];
        foreach ($finalGrades as $fg) {
            $key = "{$fg->student_id}:{$fg->academic_period_id}";
            $finalGradesMap[$key] = [
                'academic_period_id' => $fg->academic_period_id,
                'academic_period_name' => $fg->academicPeriod?->name,
                'final_entered_value' => $fg->final_entered_value,
                'numeric_score' => $fg->numeric_score !== null ? (float) $fg->numeric_score : null,
                'scale_item_name' => $fg->scaleItem?->name,
                'status' => $fg->status,
            ];
        }

        // 7. Assemble Matrix
        $studentRows = [];
        foreach ($students as $st) {
            $stGrades = [];
            foreach ($assessments as $ass) {
                $k = "{$st->id}:{$ass->id}";
                $stGrades[] = [
                    'assessment_id' => $ass->id,
                    'grade' => $gradesMap[$k] ?? null,
                ];
            }

            $stFinalGrades = [];
            if ($academicPeriodId) {
                $fk = "{$st->id}:{$academicPeriodId}";
                if (isset($finalGradesMap[$fk])) {
                    $stFinalGrades[] = $finalGradesMap[$fk];
                }
            } else {
                foreach ($finalGrades->where('student_id', $st->id) as $fg) {
                    $stFinalGrades[] = [
                        'academic_period_id' => $fg->academic_period_id,
                        'academic_period_name' => $fg->academicPeriod?->name,
                        'final_entered_value' => $fg->final_entered_value,
                        'numeric_score' => $fg->numeric_score !== null ? (float) $fg->numeric_score : null,
                        'scale_item_name' => $fg->scaleItem?->name,
                        'status' => $fg->status,
                    ];
                }
            }

            $studentRows[] = [
                'student_id' => $st->id,
                'student_code' => $st->student_code,
                'first_name' => $st->first_name,
                'last_name' => $st->last_name,
                'name' => $st->name,
                'grades' => $stGrades,
                'final_grades' => $stFinalGrades,
            ];
        }

        return [
            'course' => [
                'id' => $course->id,
                'name' => $course->name,
                'grade_name' => $course->grade?->name,
                'campus_id' => $course->campus_id,
                'campus_name' => $course->campus?->name,
            ],
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'color' => $subject->color,
            ],
            'academic_period_id' => $academicPeriodId,
            'assessments' => $assessments->map(fn($ass) => [
                'id' => $ass->id,
                'title' => $ass->title,
                'weight_percentage' => (float) $ass->weight_percentage,
                'max_score' => (float) $ass->max_score,
                'due_date' => $ass->due_date?->toDateString(),
                'academic_period_id' => $ass->academic_period_id,
                'academic_period_name' => $ass->academicPeriod?->name,
                'category_name' => $ass->category?->name,
            ])->toArray(),
            'students' => $studentRows,
        ];
    }

    /**
     * Get student's grades within active tenant school context.
     *
     * @param int $schoolId
     * @param int $studentId
     * @param array $filters
     * @return array
     * @throws InvalidArgumentException
     */
    public function getStudentGrades(int $schoolId, int $studentId, array $filters = []): array
    {
        // 1. Verify Student exists and belongs to schoolId
        $student = Student::where('id', $studentId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            throw new InvalidArgumentException("Estudiante no encontrado o no pertenece al colegio activo.");
        }

        // 2. Filter validation
        $courseIdFilter = !empty($filters['course_id']) ? (int) $filters['course_id'] : null;
        $subjectIdFilter = !empty($filters['subject_id']) ? (int) $filters['subject_id'] : null;
        $academicPeriodIdFilter = !empty($filters['academic_period_id']) ? (int) $filters['academic_period_id'] : null;

        if ($courseIdFilter) {
            $courseExists = DB::table('courses')
                ->where('id', $courseIdFilter)
                ->where('school_id', $schoolId)
                ->first();

            if (!$courseExists) {
                throw new InvalidArgumentException("El curso especificado no existe o no pertenece al colegio activo.");
            }

            $activeCampusId = CampusContext::id();
            if ($activeCampusId && (int) $courseExists->campus_id !== $activeCampusId) {
                throw new InvalidArgumentException("El curso especificado no pertenece a la sede activa.");
            }
        }

        if ($subjectIdFilter) {
            $subjectExists = DB::table('subjects')
                ->where('id', $subjectIdFilter)
                ->where('school_id', $schoolId)
                ->exists();

            if (!$subjectExists) {
                throw new InvalidArgumentException("La asignatura especificada no existe o no pertenece al colegio activo.");
            }
        }

        if ($academicPeriodIdFilter) {
            $periodExists = DB::table('academic_periods')
                ->where('id', $academicPeriodIdFilter)
                ->where('school_id', $schoolId)
                ->exists();

            if (!$periodExists) {
                throw new InvalidArgumentException("El período académico especificado no existe o no pertenece al colegio activo.");
            }
        }

        // 3. Find student course enrollments
        $courseEnrollmentQuery = CourseEnrollment::with(['course.grade', 'course.campus', 'studentEnrollment.academicYear'])
            ->where('school_id', $schoolId)
            ->whereHas('studentEnrollment', function ($q) use ($schoolId, $studentId) {
                $q->where('school_id', $schoolId)->where('student_id', $studentId);
            });

        if ($courseIdFilter) {
            $courseEnrollmentQuery->where('course_id', $courseIdFilter);
        }

        $activeCampusId = CampusContext::id();
        if ($activeCampusId) {
            $courseEnrollmentQuery->whereHas('course', function ($q) use ($activeCampusId) {
                $q->where('campus_id', $activeCampusId);
            });
        }

        $courseEnrollments = $courseEnrollmentQuery->get();
        $courseIds = $courseEnrollments->pluck('course_id')->unique()->toArray();

        // 4. Fetch Assessments
        $assessmentsQuery = Assessment::with(['subject', 'academicPeriod', 'category', 'course'])
            ->where('school_id', $schoolId)
            ->whereIn('course_id', $courseIds);

        if ($subjectIdFilter) {
            $assessmentsQuery->where('subject_id', $subjectIdFilter);
        }

        if ($academicPeriodIdFilter) {
            $assessmentsQuery->where('academic_period_id', $academicPeriodIdFilter);
        }

        $assessments = $assessmentsQuery->orderBy('due_date', 'asc')->orderBy('id', 'asc')->get();

        // 5. Fetch StudentGrades
        $assessmentIds = $assessments->pluck('id')->toArray();

        $studentGrades = StudentGrade::with(['scaleItem'])
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->whereIn('assessment_id', $assessmentIds)
            ->get()
            ->keyBy('assessment_id');

        // 6. Fetch PeriodFinalGrades
        $finalGradesQuery = PeriodFinalGrade::with(['subject', 'academicPeriod', 'scaleItem', 'course'])
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId);

        if ($courseIdFilter) {
            $finalGradesQuery->where('course_id', $courseIdFilter);
        } else {
            $finalGradesQuery->whereIn('course_id', $courseIds);
        }

        if ($subjectIdFilter) {
            $finalGradesQuery->where('subject_id', $subjectIdFilter);
        }

        if ($academicPeriodIdFilter) {
            $finalGradesQuery->where('academic_period_id', $academicPeriodIdFilter);
        }

        $finalGrades = $finalGradesQuery->get();

        // 7. Build structured output
        $assessmentEntries = [];
        foreach ($assessments as $ass) {
            $sg = $studentGrades->get($ass->id);

            $assessmentEntries[] = [
                'assessment_id' => $ass->id,
                'title' => $ass->title,
                'course_id' => $ass->course_id,
                'subject' => [
                    'id' => $ass->subject?->id,
                    'name' => $ass->subject?->name,
                    'code' => $ass->subject?->code,
                    'color' => $ass->subject?->color,
                ],
                'academic_period' => [
                    'id' => $ass->academicPeriod?->id,
                    'name' => $ass->academicPeriod?->name,
                    'period_order' => $ass->academicPeriod?->period_order,
                ],
                'weight_percentage' => (float) $ass->weight_percentage,
                'max_score' => (float) $ass->max_score,
                'due_date' => $ass->due_date?->toDateString(),
                'grade' => $sg ? [
                    'entered_value' => $sg->entered_value,
                    'normalized_value' => $sg->normalized_value,
                    'equivalent_numeric_value' => $sg->equivalent_numeric_value !== null ? (float) $sg->equivalent_numeric_value : null,
                    'display_value' => $sg->display_value,
                    'is_exempt' => (bool) $sg->is_exempt,
                    'comments' => $sg->comments,
                    'scale_item_name' => $sg->scaleItem?->name,
                ] : null,
            ];
        }

        $periodFinalGradeEntries = [];
        foreach ($finalGrades as $fg) {
            $periodFinalGradeEntries[] = [
                'course_id' => $fg->course_id,
                'subject' => [
                    'id' => $fg->subject?->id,
                    'name' => $fg->subject?->name,
                    'code' => $fg->subject?->code,
                ],
                'academic_period' => [
                    'id' => $fg->academicPeriod?->id,
                    'name' => $fg->academicPeriod?->name,
                    'period_order' => $fg->academicPeriod?->period_order,
                ],
                'final_entered_value' => $fg->final_entered_value,
                'numeric_score' => $fg->numeric_score !== null ? (float) $fg->numeric_score : null,
                'scale_item_name' => $fg->scaleItem?->name,
                'status' => $fg->status,
            ];
        }

        return [
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'name' => $student->name,
                'document_type' => $student->document_type,
                'document_number' => $student->document_number,
            ],
            'assessments' => $assessmentEntries,
            'period_final_grades' => $periodFinalGradeEntries,
        ];
    }
}
