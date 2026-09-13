<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenant\Services\ClassroomService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\CampusContext;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request, ClassroomService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $campusId = CampusContext::id() ?? $request->input('campus_id');
        $classrooms = $service->listClassrooms($schoolId, $campusId);
        return response()->json(['data' => $classrooms]);
    }

    public function store(Request $request, ClassroomService $service): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => 'required|integer',
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:100',
            'type' => 'nullable|string|max:30',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
        ]);

        $schoolId = TenantContext::id();
        $classroom = $service->createClassroom($schoolId, $validated);

        return response()->json(['message' => 'Aula creada exitosamente.', 'data' => $classroom], 201);
    }
}
