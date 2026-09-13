<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Grading\Services\GradingQueryService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GradingQueryController extends Controller
{
    /**
     * Get grades matrix for a course and subject.
     */
    public function gradesMatrix(Request $request, int $course, int $subject, GradingQueryService $service): JsonResponse
    {
        $validated = $request->validate([
            'academic_period_id' => 'nullable|integer',
        ]);

        $schoolId = TenantContext::id();

        try {
            $matrix = $service->getGradesMatrix($schoolId, $course, $subject, $validated);

            return response()->json(['data' => $matrix]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get grades for a specific student within active tenant school context.
     */
    public function studentGrades(Request $request, int $student, GradingQueryService $service): JsonResponse
    {
        $validated = $request->validate([
            'academic_period_id' => 'nullable|integer',
            'course_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $schoolId = TenantContext::id();

        try {
            $result = $service->getStudentGrades($schoolId, $student, $validated);

            return response()->json(['data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
