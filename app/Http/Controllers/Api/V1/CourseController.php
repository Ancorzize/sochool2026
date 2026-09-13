<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Academic\Services\CourseService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\CampusContext;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CourseController extends Controller
{
    public function index(Request $request, CourseService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $campusId = CampusContext::id() ?? ($request->input('campus_id') ? (int) $request->input('campus_id') : null);
        $academicYearId = $request->input('academic_year_id') ? (int) $request->input('academic_year_id') : null;

        $courses = $service->listCourses($schoolId, $campusId, $academicYearId);
        return response()->json(['data' => $courses]);
    }

    public function store(Request $request, CourseService $service): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'grade_id' => 'required|integer',
            'director_teacher_id' => 'nullable|integer',
            'name' => 'required|string|max:50',
            'shift' => 'nullable|string|max:30',
        ]);

        $schoolId = TenantContext::id();

        try {
            $course = $service->createCourse($schoolId, $validated);
            return response()->json(['message' => 'Curso creado exitosamente.', 'data' => $course], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function assignTeacher(Request $request, int $id, CourseService $service): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|integer',
            'teacher_id' => 'required|integer',
            'hours_per_week' => 'nullable|integer|min:1|max:40',
        ]);

        $schoolId = TenantContext::id();

        try {
            $assignment = $service->assignTeacher($schoolId, $id, $validated);
            return response()->json(['message' => 'Docente asignado al curso exitosamente.', 'data' => $assignment], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
