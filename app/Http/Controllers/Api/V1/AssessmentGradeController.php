<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Grading\Services\AssessmentGradeService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssessmentGradeController extends Controller
{
    /**
     * Bulk record or update student grades for an assessment.
     */
    public function store(Request $request, int $assessment, AssessmentGradeService $service): JsonResponse
    {
        $validated = $request->validate([
            'grades' => 'required|array|min:1',
            'grades.*.student_id' => 'required|integer',
            'grades.*.entered_value' => 'required',
            'grades.*.is_exempt' => 'nullable|boolean',
            'grades.*.comments' => 'nullable|string|max:1000',
        ]);

        $schoolId = TenantContext::id();
        $userId = $request->user()?->id;

        try {
            $result = $service->recordAssessmentGrades($schoolId, $assessment, $validated['grades'], $userId);

            return response()->json([
                'message' => 'Calificaciones registradas exitosamente.',
                'data' => $result,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
