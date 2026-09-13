<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Services\AttendanceService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'student_id' => 'nullable|integer',
            'attendance_status_id' => 'nullable|integer',
            'date' => 'nullable|date',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $schoolId = TenantContext::id();

        $attendances = $service->listAttendance($schoolId, $validated);

        return response()->json(['data' => $attendances]);
    }

    public function store(Request $request, AttendanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer',
            'subject_id' => 'nullable|integer',
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|integer',
            'records.*.attendance_status_id' => 'required|integer',
            'records.*.is_justified' => 'nullable|boolean',
            'records.*.remarks' => 'nullable|string|max:500',
        ]);

        $schoolId = TenantContext::id();
        $userId = $request->user()->id;

        try {
            $result = $service->recordBatch($schoolId, $validated, $userId);

            return response()->json([
                'message' => 'Asistencia registrada exitosamente.',
                'data' => $result,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, int $id, AttendanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'attendance_status_id' => 'sometimes|required|integer',
            'is_justified' => 'sometimes|nullable|boolean',
            'remarks' => 'sometimes|nullable|string|max:500',
        ]);

        $schoolId = TenantContext::id();
        $userId = $request->user()->id;

        try {
            $attendance = $service->updateAttendance($schoolId, $id, $validated, $userId);

            return response()->json([
                'message' => 'Registro de asistencia actualizado exitosamente.',
                'data' => $attendance,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function studentAttendance(Request $request, int $student, AttendanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'attendance_status_id' => 'nullable|integer',
            'date' => 'nullable|date',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $schoolId = TenantContext::id();

        try {
            $attendances = $service->getStudentAttendance($schoolId, $student, $validated);

            return response()->json(['data' => $attendances]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
