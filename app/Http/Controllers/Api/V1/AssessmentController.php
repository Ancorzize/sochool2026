<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Grading\Services\AssessmentService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssessmentController extends Controller
{
    /**
     * Create assessment(s) for a course or multiple courses.
     */
    public function store(Request $request, AssessmentService $service): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required_without:course_ids|nullable|integer',
            'course_ids' => 'required_without:course_id|nullable|array|min:1',
            'course_ids.*' => 'integer',
            'subject_id' => 'required|integer',
            'academic_period_id' => 'required|integer',
            'assessment_category_id' => 'nullable|integer',
            'teacher_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'weight_percentage' => 'required|numeric|min:0.01|max:100.00',
            'due_date' => 'nullable|date',
            'max_score' => 'nullable|numeric|min:0.01|max:100.00',
        ]);

        $schoolId = TenantContext::id();
        $userId = $request->user()?->id;

        try {
            $assessments = $service->createAssessments($schoolId, $validated, $userId);

            return response()->json([
                'message' => 'Evaluación(es) creada(s) exitosamente.',
                'data' => $assessments,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * List assessments for a course and subject.
     */
    public function indexForCourseSubject(Request $request, int $course, int $subject, AssessmentService $service): JsonResponse
    {
        $validated = $request->validate([
            'academic_period_id' => 'nullable|integer',
        ]);

        $schoolId = TenantContext::id();

        try {
            $assessments = $service->listCourseSubjectAssessments($schoolId, $course, $subject, $validated);

            return response()->json(['data' => $assessments]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
