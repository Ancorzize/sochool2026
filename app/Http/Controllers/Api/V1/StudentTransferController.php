<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Student\Services\StudentTransferService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTransferController extends Controller
{
    public function courseTransfer(Request $request, StudentTransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'from_course_id' => 'required|integer',
            'to_course_id' => 'required|integer',
            'reason' => 'nullable|string',
        ]);

        $schoolId = TenantContext::id();
        $userId = $request->user()->id;

        try {
            $transfer = $service->courseTransfer(
                $schoolId,
                $validated['student_id'],
                $validated['academic_year_id'],
                $validated['from_course_id'],
                $validated['to_course_id'],
                $validated['reason'] ?? null,
                $userId
            );

            return response()->json(['message' => 'Traslado de curso procesado.', 'data' => $transfer], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function campusTransfer(Request $request, StudentTransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'from_campus_id' => 'required|integer',
            'to_campus_id' => 'required|integer',
            'to_course_id' => 'required|integer',
            'reason' => 'nullable|string',
        ]);

        $schoolId = TenantContext::id();
        $userId = $request->user()->id;

        try {
            $transfer = $service->campusTransfer(
                $schoolId,
                $validated['student_id'],
                $validated['academic_year_id'],
                $validated['from_campus_id'],
                $validated['to_campus_id'],
                $validated['to_course_id'],
                $validated['reason'] ?? null,
                $userId
            );

            return response()->json(['message' => 'Traslado de sede procesado.', 'data' => $transfer], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
