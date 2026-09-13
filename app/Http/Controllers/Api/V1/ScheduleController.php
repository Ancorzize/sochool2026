<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Academic\Services\ScheduleService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\CampusContext;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request, ScheduleService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $campusId = CampusContext::id() ?? $request->input('campus_id');
        $courseId = $request->input('course_id');
        $teacherId = $request->input('teacher_id');
        $classroomId = $request->input('classroom_id');

        $schedules = $service->listSchedules($schoolId, $campusId, $courseId, $teacherId, $classroomId);
        return response()->json(['data' => $schedules]);
    }

    public function store(Request $request, ScheduleService $service): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'course_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'teacher_id' => 'required|integer',
            'classroom_id' => 'nullable|integer',
            'day_of_week' => 'required|integer|min:1|max:7',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
        ]);

        $schoolId = TenantContext::id();

        try {
            $schedule = $service->createSchedule($schoolId, $validated);
            return response()->json(['message' => 'Horario registrado exitosamente.', 'data' => $schedule], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, int $id, ScheduleService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $campusId = CampusContext::id() ?? ($request->input('campus_id') ? (int) $request->input('campus_id') : null);

        try {
            $schedule = $service->delete($schoolId, $id, $campusId);
            return response()->json(['message' => 'Horario desactivado exitosamente.', 'data' => $schedule], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
