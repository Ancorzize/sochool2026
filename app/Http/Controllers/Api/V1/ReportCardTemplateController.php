<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\ReportCard\Models\ReportCardAssignment;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\ReportCard\Services\ReportCardTemplateService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportCardTemplateController extends Controller
{
    public function __construct(
        protected ReportCardTemplateService $templateService
    ) {}

    /**
     * List all templates for current active tenant
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = TenantContext::id();

        $query = ReportCardTemplate::where('school_id', $schoolId)
            ->with(['sections.fields']);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $templates = $query->orderBy('name')->get();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Store a new template with optional nested sections and fields
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'layout_config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'sections' => 'nullable|array',
            'sections.*.section_type' => 'required|string|max:100',
            'sections.*.title' => 'required|string|max:255',
            'sections.*.section_order' => 'nullable|integer',
            'sections.*.config' => 'nullable|array',
            'sections.*.fields' => 'nullable|array',
            'sections.*.fields.*.field_key' => 'required|string|max:100',
            'sections.*.fields.*.label' => 'required|string|max:255',
            'sections.*.fields.*.is_visible' => 'nullable|boolean',
            'sections.*.fields.*.field_order' => 'nullable|integer',
        ]);

        $template = $this->templateService->createTemplate($validated);

        return response()->json([
            'message' => 'Plantilla de boletín creada exitosamente.',
            'data' => $template,
        ], 201);
    }

    /**
     * Show a template by ID
     */
    public function show(int $id): JsonResponse
    {
        $schoolId = TenantContext::id();

        $template = ReportCardTemplate::where('school_id', $schoolId)
            ->with(['sections.fields'])
            ->findOrFail($id);

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Update a template
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $schoolId = TenantContext::id();
        $template = ReportCardTemplate::where('school_id', $schoolId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'layout_config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'sections' => 'nullable|array',
            'sections.*.section_type' => 'required_with:sections|string|max:100',
            'sections.*.title' => 'required_with:sections|string|max:255',
            'sections.*.section_order' => 'nullable|integer',
            'sections.*.config' => 'nullable|array',
            'sections.*.fields' => 'nullable|array',
            'sections.*.fields.*.field_key' => 'required_with:sections.*.fields|string|max:100',
            'sections.*.fields.*.label' => 'required_with:sections.*.fields|string|max:255',
            'sections.*.fields.*.is_visible' => 'nullable|boolean',
            'sections.*.fields.*.field_order' => 'nullable|integer',
        ]);

        $updated = $this->templateService->updateTemplate($template, $validated);

        return response()->json([
            'message' => 'Plantilla de boletín actualizada exitosamente.',
            'data' => $updated,
        ]);
    }

    /**
     * Duplicate a template
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $schoolId = TenantContext::id();
        $sourceTemplate = ReportCardTemplate::where('school_id', $schoolId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $cloned = $this->templateService->duplicateTemplate($sourceTemplate, $validated['name'] ?? null);

        return response()->json([
            'message' => 'Plantilla duplicada exitosamente.',
            'data' => $cloned,
        ], 201);
    }

    /**
     * Create single scope assignment
     */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|integer',
            'educational_level_id' => 'nullable|integer',
            'grade_id' => 'nullable|integer',
            'course_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $assignment = $this->templateService->assignTemplate($validated);

        return response()->json([
            'message' => 'Plantilla asignada exitosamente.',
            'data' => $assignment->load(['template', 'educationalLevel', 'grade', 'course', 'subject']),
        ], 201);
    }

    /**
     * List all assignments for tenant
     */
    public function listAssignments(Request $request): JsonResponse
    {
        $schoolId = TenantContext::id();

        $assignments = ReportCardAssignment::where('school_id', $schoolId)
            ->with(['template', 'educationalLevel', 'grade', 'course', 'subject'])
            ->get();

        return response()->json([
            'data' => $assignments,
        ]);
    }

    /**
     * Delete an assignment
     */
    public function deleteAssignment(int $id): JsonResponse
    {
        $schoolId = TenantContext::id();

        $assignment = ReportCardAssignment::where('school_id', $schoolId)->findOrFail($id);
        $assignment->delete();

        return response()->json([
            'message' => 'Asignación de plantilla eliminada exitosamente.',
        ]);
    }

    /**
     * Mass apply template across target scopes with independent cloning option
     */
    public function massApply(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'target_scopes' => 'required|array|min:1',
            'target_scopes.*.educational_level_id' => 'nullable|integer',
            'target_scopes.*.grade_id' => 'nullable|integer',
            'target_scopes.*.course_id' => 'nullable|integer',
            'target_scopes.*.subject_id' => 'nullable|integer',
            'independent' => 'nullable|boolean',
        ]);

        $independent = $validated['independent'] ?? true;

        $assignments = $this->templateService->massApplyTemplate(
            $id,
            $validated['target_scopes'],
            $independent
        );

        return response()->json([
            'message' => 'Plantilla aplicada masivamente exitosamente.',
            'data' => $assignments,
        ], 201);
    }

    /**
     * Resolve template for course and optional subject context
     */
    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $schoolId = TenantContext::id();

        $template = $this->templateService->resolveTemplateForContext(
            $schoolId,
            $validated['course_id'],
            $validated['subject_id'] ?? null
        );

        return response()->json([
            'data' => $template->load(['sections.fields']),
        ]);
    }
}
