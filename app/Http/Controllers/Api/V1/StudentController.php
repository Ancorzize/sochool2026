<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Student\Models\Student;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = Student::all();

        return response()->json([
            'school_id' => TenantContext::id(),
            'data' => $students,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'document_type' => 'required|string|max:10',
            'document_number' => 'required|string|max:30',
            'gender' => 'required|string|in:M,F,OTHER',
            'birth_date' => 'required|date',
        ]);

        $student = Student::create([
            'uuid' => (string) Str::uuid(),
            'school_id' => TenantContext::id(),
            'student_code' => 'EST-API-' . Str::random(4),
            'document_type' => $validated['document_type'],
            'document_number' => $validated['document_number'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'],
            'nationality' => 'Colombiana',
            'city' => 'Bogotá',
            'academic_status' => 'ACTIVE',
        ]);

        return response()->json([
            'message' => 'Estudiante creado exitosamente.',
            'data' => $student,
        ], 201);
    }
}
