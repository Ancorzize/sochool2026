<?php

namespace App\Domain\ReportCard\Services;

use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Academic\Models\Subject;
use App\Domain\ReportCard\Models\ReportCardAssignment;
use App\Domain\ReportCard\Models\ReportCardField;
use App\Domain\ReportCard\Models\ReportCardSection;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportCardTemplateService
{
    public function __construct(
        protected ReportCardEngine $reportCardEngine
    ) {}

    /**
     * Create a template with nested sections and fields
     */
    public function createTemplate(array $data): ReportCardTemplate
    {
        $schoolId = TenantContext::id();

        return DB::transaction(function () use ($schoolId, $data) {
            $template = ReportCardTemplate::create([
                'school_id' => $schoolId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'header_html' => $data['header_html'] ?? null,
                'footer_html' => $data['footer_html'] ?? null,
                'layout_config' => $data['layout_config'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['sections']) && is_array($data['sections'])) {
                foreach ($data['sections'] as $secIndex => $secData) {
                    $section = ReportCardSection::create([
                        'school_id' => $schoolId,
                        'template_id' => $template->id,
                        'section_type' => $secData['section_type'],
                        'title' => $secData['title'],
                        'section_order' => $secData['section_order'] ?? ($secIndex + 1),
                        'config' => $secData['config'] ?? null,
                    ]);

                    if (!empty($secData['fields']) && is_array($secData['fields'])) {
                        foreach ($secData['fields'] as $fIndex => $fData) {
                            ReportCardField::create([
                                'school_id' => $schoolId,
                                'section_id' => $section->id,
                                'field_key' => $fData['field_key'],
                                'label' => $fData['label'],
                                'is_visible' => $fData['is_visible'] ?? true,
                                'field_order' => $fData['field_order'] ?? ($fIndex + 1),
                            ]);
                        }
                    }
                }
            }

            return $template->load('sections.fields');
        });
    }

    /**
     * Update template details, sections, and fields
     */
    public function updateTemplate(ReportCardTemplate $template, array $data): ReportCardTemplate
    {
        $schoolId = TenantContext::id();

        if ($template->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'template' => ['Plantilla no pertenece al colegio activo.'],
            ]);
        }

        return DB::transaction(function () use ($template, $schoolId, $data) {
            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $template->description,
                'header_html' => array_key_exists('header_html', $data) ? $data['header_html'] : $template->header_html,
                'footer_html' => array_key_exists('footer_html', $data) ? $data['footer_html'] : $template->footer_html,
                'layout_config' => array_key_exists('layout_config', $data) ? $data['layout_config'] : $template->layout_config,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $template->is_active,
            ]);

            if (isset($data['sections']) && is_array($data['sections'])) {
                // Delete existing sections and fields to replace with updated structure
                $existingSectionIds = $template->sections()->pluck('id');
                ReportCardField::whereIn('section_id', $existingSectionIds)->delete();
                $template->sections()->delete();

                foreach ($data['sections'] as $secIndex => $secData) {
                    $section = ReportCardSection::create([
                        'school_id' => $schoolId,
                        'template_id' => $template->id,
                        'section_type' => $secData['section_type'],
                        'title' => $secData['title'],
                        'section_order' => $secData['section_order'] ?? ($secIndex + 1),
                        'config' => $secData['config'] ?? null,
                    ]);

                    if (!empty($secData['fields']) && is_array($secData['fields'])) {
                        foreach ($secData['fields'] as $fIndex => $fData) {
                            ReportCardField::create([
                                'school_id' => $schoolId,
                                'section_id' => $section->id,
                                'field_key' => $fData['field_key'],
                                'label' => $fData['label'],
                                'is_visible' => $fData['is_visible'] ?? true,
                                'field_order' => $fData['field_order'] ?? ($fIndex + 1),
                            ]);
                        }
                    }
                }
            }

            return $template->fresh(['sections.fields']);
        });
    }

    /**
     * Deep copy template, sections, and fields
     */
    public function duplicateTemplate(ReportCardTemplate $sourceTemplate, ?string $newName = null): ReportCardTemplate
    {
        $schoolId = TenantContext::id();

        if ($sourceTemplate->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'template' => ['Plantilla no pertenece al colegio activo.'],
            ]);
        }

        return DB::transaction(function () use ($sourceTemplate, $schoolId, $newName) {
            $targetName = $newName ?? "{$sourceTemplate->name} (Copia)";

            // Ensure unique name per school
            $existingCount = ReportCardTemplate::where('school_id', $schoolId)
                ->where('name', 'LIKE', "{$targetName}%")
                ->count();
            if ($existingCount > 0 && !$newName) {
                $targetName = "{$sourceTemplate->name} (Copia " . ($existingCount + 1) . ")";
            }

            $newTemplate = ReportCardTemplate::create([
                'school_id' => $schoolId,
                'name' => $targetName,
                'description' => $sourceTemplate->description,
                'header_html' => $sourceTemplate->header_html,
                'footer_html' => $sourceTemplate->footer_html,
                'layout_config' => $sourceTemplate->layout_config,
                'is_active' => $sourceTemplate->is_active,
            ]);

            $sourceTemplate->loadMissing('sections.fields');

            foreach ($sourceTemplate->sections as $sec) {
                $newSection = ReportCardSection::create([
                    'school_id' => $schoolId,
                    'template_id' => $newTemplate->id,
                    'section_type' => $sec->section_type,
                    'title' => $sec->title,
                    'section_order' => $sec->section_order,
                    'config' => $sec->config,
                ]);

                foreach ($sec->fields as $field) {
                    ReportCardField::create([
                        'school_id' => $schoolId,
                        'section_id' => $newSection->id,
                        'field_key' => $field->field_key,
                        'label' => $field->label,
                        'is_visible' => $field->is_visible,
                        'field_order' => $field->field_order,
                    ]);
                }
            }

            return $newTemplate->load('sections.fields');
        });
    }

    /**
     * Create single scope assignment with tenant entity validation
     */
    public function assignTemplate(array $data): ReportCardAssignment
    {
        $schoolId = TenantContext::id();

        $template = ReportCardTemplate::where('school_id', $schoolId)->findOrFail($data['template_id']);

        $eduLevelId = $data['educational_level_id'] ?? null;
        $gradeId = $data['grade_id'] ?? null;
        $courseId = $data['course_id'] ?? null;
        $subjectId = $data['subject_id'] ?? null;

        $this->validateScopeEntities($schoolId, $eduLevelId, $gradeId, $courseId, $subjectId);

        return ReportCardAssignment::updateOrCreate(
            [
                'school_id' => $schoolId,
                'educational_level_id' => $eduLevelId,
                'grade_id' => $gradeId,
                'course_id' => $courseId,
                'subject_id' => $subjectId,
            ],
            [
                'template_id' => $template->id,
            ]
        );
    }

    /**
     * Mass apply template to multiple target scopes
     * When $independent = true, clones template+sections+fields for each target scope.
     */
    public function massApplyTemplate(int $templateId, array $targetScopes, bool $independent = true): array
    {
        $schoolId = TenantContext::id();
        $sourceTemplate = ReportCardTemplate::where('school_id', $schoolId)->findOrFail($templateId);

        if (empty($targetScopes)) {
            throw ValidationException::withMessages([
                'target_scopes' => ['Debe especificar al menos un alcance destino.'],
            ]);
        }

        $assignments = [];

        DB::transaction(function () use ($schoolId, $sourceTemplate, $targetScopes, $independent, &$assignments) {
            foreach ($targetScopes as $scope) {
                $eduLevelId = $scope['educational_level_id'] ?? null;
                $gradeId = $scope['grade_id'] ?? null;
                $courseId = $scope['course_id'] ?? null;
                $subjectId = $scope['subject_id'] ?? null;

                $this->validateScopeEntities($schoolId, $eduLevelId, $gradeId, $courseId, $subjectId);

                $targetTemplate = $sourceTemplate;

                if ($independent) {
                    $scopeSuffix = $this->buildScopeSuffix($schoolId, $eduLevelId, $gradeId, $courseId, $subjectId);
                    $targetName = "{$sourceTemplate->name} - {$scopeSuffix}";
                    $targetTemplate = $this->duplicateTemplate($sourceTemplate, $targetName);
                }

                $assignment = ReportCardAssignment::updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'educational_level_id' => $eduLevelId,
                        'grade_id' => $gradeId,
                        'course_id' => $courseId,
                        'subject_id' => $subjectId,
                    ],
                    [
                        'template_id' => $targetTemplate->id,
                    ]
                );

                $assignments[] = $assignment->load(['template', 'educationalLevel', 'grade', 'course', 'subject']);
            }
        });

        return $assignments;
    }

    /**
     * Resolve matching template for course and optional subject using precedence hierarchy
     */
    public function resolveTemplateForContext(int $schoolId, int $courseId, ?int $subjectId = null): ReportCardTemplate
    {
        $course = Course::where('school_id', $schoolId)->with('grade')->findOrFail($courseId);

        if ($subjectId) {
            Subject::where('school_id', $schoolId)->findOrFail($subjectId);
        }

        return $this->reportCardEngine->resolveTemplate($schoolId, $course, $subjectId);
    }

    /**
     * Validate that all non-null scope entities belong to active tenant
     */
    protected function validateScopeEntities(
        int $schoolId,
        ?int $eduLevelId,
        ?int $gradeId,
        ?int $courseId,
        ?int $subjectId
    ): void {
        if ($eduLevelId && !EducationalLevel::where('school_id', $schoolId)->where('id', $eduLevelId)->exists()) {
            throw ValidationException::withMessages([
                'educational_level_id' => ['El nivel educativo no existe o no pertenece al colegio.'],
            ]);
        }

        if ($gradeId && !Grade::where('school_id', $schoolId)->where('id', $gradeId)->exists()) {
            throw ValidationException::withMessages([
                'grade_id' => ['El grado no existe o no pertenece al colegio.'],
            ]);
        }

        if ($courseId) {
            $course = Course::where('school_id', $schoolId)->where('id', $courseId)->first();
            if (!$course) {
                throw ValidationException::withMessages([
                    'course_id' => ['El curso no existe o no pertenece al colegio.'],
                ]);
            }
            if ($gradeId && $course->grade_id !== $gradeId) {
                throw ValidationException::withMessages([
                    'course_id' => ['El curso no pertenece al grado especificado.'],
                ]);
            }
        }

        if ($subjectId && !Subject::where('school_id', $schoolId)->where('id', $subjectId)->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => ['La asignatura no existe o no pertenece al colegio.'],
            ]);
        }
    }

    /**
     * Build scope suffix name for independent cloned templates
     */
    protected function buildScopeSuffix(
        int $schoolId,
        ?int $eduLevelId,
        ?int $gradeId,
        ?int $courseId,
        ?int $subjectId
    ): string {
        $parts = [];

        if ($courseId) {
            $course = Course::find($courseId);
            $parts[] = "Curso " . ($course?->name ?? $courseId);
        } elseif ($gradeId) {
            $grade = Grade::find($gradeId);
            $parts[] = "Grado " . ($grade?->name ?? $gradeId);
        } elseif ($eduLevelId) {
            $level = EducationalLevel::find($eduLevelId);
            $parts[] = "Nivel " . ($level?->name ?? $eduLevelId);
        }

        if ($subjectId) {
            $subject = Subject::find($subjectId);
            $parts[] = "Materia " . ($subject?->name ?? $subjectId);
        }

        return !empty($parts) ? implode(' - ', $parts) : 'Copia Independiente';
    }
}
