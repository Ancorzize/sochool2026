<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Academic\Services\AcademicYearService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index(AcademicYearService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $years = $service->listYears($schoolId);
        return response()->json(['data' => $years]);
    }

    public function store(Request $request, AcademicYearService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|string|in:UPCOMING,ACTIVE,CLOSED',
        ]);

        $schoolId = TenantContext::id();
        $year = $service->createYear($schoolId, $validated);

        return response()->json(['message' => 'Año académico creado.', 'data' => $year], 201);
    }

    public function activate(int $id, AcademicYearService $service): JsonResponse
    {
        $schoolId = TenantContext::id();
        $year = $service->activateYear($schoolId, $id);

        return response()->json(['message' => 'Año académico activado.', 'data' => $year]);
    }
}
