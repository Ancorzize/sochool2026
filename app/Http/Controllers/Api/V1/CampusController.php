<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenant\Services\CampusService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampusController extends Controller
{
    public function index(CampusService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $campuses = $service->listCampuses($schoolId);
        return response()->json(['data' => $campuses]);
    }

    public function store(Request $request, CampusService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:30',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
            'is_main' => 'nullable|boolean',
        ]);

        $schoolId = TenantContext::id();
        $campus = $service->createCampus($schoolId, $validated);

        return response()->json(['message' => 'Sede creada exitosamente.', 'data' => $campus], 201);
    }

    public function setMain(int $id, CampusService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $campus = $service->setMainCampus($schoolId, $id);

        return response()->json(['message' => 'Sede principal actualizada.', 'data' => $campus]);
    }
}
