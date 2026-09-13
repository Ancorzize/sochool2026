<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Academic\Services\EnrollmentService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EnrollmentController extends Controller
{
    public function store(Request $request, EnrollmentService $service): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => 'required|integer',
            'student_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'grade_id' => 'required|integer',
            'course_id' => 'required|integer',
            'enrollment_number' => 'nullable|string|max:50',
            'enrollment_date' => 'nullable|date',
            'enrolled_at' => 'nullable|date',
        ]);

        $schoolId = TenantContext::id();

        try {
            $enrollment = $service->createEnrollment($schoolId, $validated);
            return response()->json([
                'message' => 'Matrícula creada exitosamente.',
                'data' => $enrollment,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
