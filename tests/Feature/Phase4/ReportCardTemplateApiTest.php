<?php

namespace Tests\Feature\Phase4;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Tenant\Models\Campus;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Academic\Models\Subject;
use App\Domain\ReportCard\Models\ReportCardAssignment;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\Permission;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportCardTemplateApiTest extends TestCase
{
    protected School $schoolA;
    protected School $schoolB;
    protected User $userA;
    protected User $userB;
    protected Campus $campusA;
    protected Campus $campusB;
    protected EducationalLevel $levelA;
    protected Grade $grade11A;
    protected Course $course11A;
    protected Course $course11B;
    protected Subject $subjectPhysics;
    protected Subject $subjectMath;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        DB::connection('pgsql')->statement("SET app.bypass_rls = 'on';");

        // 1. Create School A & School B
        $this->schoolA = School::factory()->create(['name' => 'Colegio La Matia']);
        $this->schoolB = School::factory()->create(['name' => 'Colegio San Juan']);

        // 2. Setup Permissions
        $permView = Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        $permGen = Permission::firstOrCreate(['name' => 'reports.generate', 'guard_name' => 'web']);

        // Setup School A User & Roles
        RlsManager::setTenantContext($this->schoolA->id);
        $roleA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'ADMIN_A', 'guard_name' => 'web']);
        $roleA->permissions()->sync([$permView->id, $permGen->id]);

        $this->userA = User::factory()->create();
        $linkA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userA->id, 'status' => 'ACTIVE']);
        $linkA->roles()->sync([$roleA->id => ['school_id' => $this->schoolA->id]]);

        // Setup School B User & Roles
        RlsManager::setTenantContext($this->schoolB->id);
        $roleB = Role::create(['school_id' => $this->schoolB->id, 'name' => 'ADMIN_B', 'guard_name' => 'web']);
        $roleB->permissions()->sync([$permView->id, $permGen->id]);

        $this->userB = User::factory()->create();
        $linkB = SchoolUser::create(['school_id' => $this->schoolB->id, 'user_id' => $this->userB->id, 'status' => 'ACTIVE']);
        $linkB->roles()->sync([$roleB->id => ['school_id' => $this->schoolB->id]]);

        // 4. Create Academic Entities for School A
        RlsManager::setTenantContext($this->schoolA->id);
        $this->campusA = Campus::create(['school_id' => $this->schoolA->id, 'name' => 'Sede Principal', 'code' => 'SP', 'is_main' => true]);
        $yearA = AcademicYear::create(['school_id' => $this->schoolA->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'status' => 'ACTIVE', 'is_current' => true]);

        $this->levelA = EducationalLevel::create(['school_id' => $this->schoolA->id, 'name' => 'Secundaria', 'code' => 'SEC', 'level_order' => 2]);
        $this->grade11A = Grade::create(['school_id' => $this->schoolA->id, 'educational_level_id' => $this->levelA->id, 'name' => 'Grado 11°', 'code' => 'G11', 'grade_order' => 11]);

        $this->course11A = Course::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'academic_year_id' => $yearA->id, 'grade_id' => $this->grade11A->id, 'name' => '11A', 'shift' => 'MAÑANA']);
        $this->course11B = Course::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'academic_year_id' => $yearA->id, 'grade_id' => $this->grade11A->id, 'name' => '11B', 'shift' => 'MAÑANA']);

        $this->subjectPhysics = Subject::create(['school_id' => $this->schoolA->id, 'name' => 'Física', 'code' => 'FIS-11']);
        $this->subjectMath = Subject::create(['school_id' => $this->schoolA->id, 'name' => 'Matemáticas', 'code' => 'MAT-11']);

        // 5. Create Academic Entities for School B
        RlsManager::setTenantContext($this->schoolB->id);
        $this->campusB = Campus::create(['school_id' => $this->schoolB->id, 'name' => 'Sede B', 'code' => 'SB', 'is_main' => true]);

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
    }

    protected function authHeaders(User $user, School $school, ?Campus $campus = null): array
    {
        $token = $user->createToken('test_token', ["tenant:{$school->id}"])->plainTextToken;

        $headers = [
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID' => $school->id,
            'Accept' => 'application/json',
        ];
        if ($campus) {
            $headers['X-Campus-ID'] = $campus->id;
        }
        return $headers;
    }

    public function test_can_create_report_card_template_with_sections_and_fields(): void
    {
        $response = $this->postJson('/api/v1/report-card-templates', [
            'name' => 'Boletín Estándar 11°',
            'description' => 'Plantilla oficial de calificaciones finales',
            'header_html' => '<h1>Colegio La Matia</h1>',
            'footer_html' => '<p>Firma Director</p>',
            'layout_config' => ['font_size' => '12pt', 'margin' => '2cm'],
            'is_active' => true,
            'sections' => [
                [
                    'section_type' => 'HEADER',
                    'title' => 'Encabezado Institucional',
                    'section_order' => 1,
                    'fields' => [
                        ['field_key' => 'show_logo', 'label' => 'Mostrar Logo', 'is_visible' => true, 'field_order' => 1],
                    ],
                ],
                [
                    'section_type' => 'GRADES_SUMMARY',
                    'title' => 'Resumen de Calificaciones',
                    'section_order' => 2,
                    'fields' => [
                        ['field_key' => 'show_weighted_average', 'label' => 'Mostrar Promedio', 'is_visible' => true, 'field_order' => 1],
                        ['field_key' => 'show_ranking', 'label' => 'Mostrar Puesto', 'is_visible' => false, 'field_order' => 2],
                    ],
                ],
            ],
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Boletín Estándar 11°')
            ->assertJsonCount(2, 'data.sections');

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertDatabaseHas('report_card_templates', [
            'school_id' => $this->schoolA->id,
            'name' => 'Boletín Estándar 11°',
        ]);
    }

    public function test_can_list_and_filter_templates_by_tenant(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Plantilla A1', 'is_active' => true]);
        ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Plantilla A2', 'is_active' => false]);

        $response = $this->getJson('/api/v1/report-card-templates?is_active=true', $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Plantilla A1');
    }

    public function test_can_update_template_structure_and_sections(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $tpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Plantilla Original', 'is_active' => true]);

        $response = $this->putJson("/api/v1/report-card-templates/{$tpl->id}", [
            'name' => 'Plantilla Modificada',
            'sections' => [
                [
                    'section_type' => 'OBSERVATIONS',
                    'title' => 'Observaciones del Director',
                    'section_order' => 1,
                    'fields' => [
                        ['field_key' => 'show_comments', 'label' => 'Mostrar Comentarios', 'is_visible' => true, 'field_order' => 1],
                    ],
                ],
            ],
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Plantilla Modificada')
            ->assertJsonCount(1, 'data.sections');
    }

    public function test_can_duplicate_template_with_deep_copy(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $tpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Plantilla Base', 'is_active' => true]);

        $response = $this->postJson("/api/v1/report-card-templates/{$tpl->id}/duplicate", [
            'name' => 'Plantilla Base Duplicada',
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Plantilla Base Duplicada');

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertDatabaseHas('report_card_templates', [
            'school_id' => $this->schoolA->id,
            'name' => 'Plantilla Base Duplicada',
        ]);
    }

    public function test_can_assign_template_to_educational_level_grade_course_and_subject(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $tpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Plantilla Física 11A', 'is_active' => true]);

        $response = $this->postJson('/api/v1/report-card-assignments', [
            'template_id' => $tpl->id,
            'course_id' => $this->course11A->id,
            'subject_id' => $this->subjectPhysics->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(201)
            ->assertJsonPath('data.course_id', $this->course11A->id)
            ->assertJsonPath('data.subject_id', $this->subjectPhysics->id);

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertDatabaseHas('report_card_assignments', [
            'school_id' => $this->schoolA->id,
            'template_id' => $tpl->id,
            'course_id' => $this->course11A->id,
            'subject_id' => $this->subjectPhysics->id,
        ]);
    }

    public function test_template_resolution_precedence_course_subject_over_course_over_grade_over_level(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);

        $defaultTpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Default Template', 'is_active' => true]);
        $levelTpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Level Template', 'is_active' => true]);
        $gradeTpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Grade Template', 'is_active' => true]);
        $courseTpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Course Template', 'is_active' => true]);
        $courseSubjTpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Course+Subject Template', 'is_active' => true]);

        // Create assignments at all levels
        ReportCardAssignment::create(['school_id' => $this->schoolA->id, 'template_id' => $levelTpl->id, 'educational_level_id' => $this->levelA->id]);
        ReportCardAssignment::create(['school_id' => $this->schoolA->id, 'template_id' => $gradeTpl->id, 'grade_id' => $this->grade11A->id]);
        ReportCardAssignment::create(['school_id' => $this->schoolA->id, 'template_id' => $courseTpl->id, 'course_id' => $this->course11A->id]);
        ReportCardAssignment::create(['school_id' => $this->schoolA->id, 'template_id' => $courseSubjTpl->id, 'course_id' => $this->course11A->id, 'subject_id' => $this->subjectPhysics->id]);

        // 1. Resolve for Course11A + Physics -> Should resolve Course+Subject Template
        $res1 = $this->getJson("/api/v1/report-card-templates/resolve?course_id={$this->course11A->id}&subject_id={$this->subjectPhysics->id}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res1->assertStatus(200)->assertJsonPath('data.id', $courseSubjTpl->id);

        // 2. Resolve for Course11A + Math (no specific subject assignment) -> Should resolve Course Template
        $res2 = $this->getJson("/api/v1/report-card-templates/resolve?course_id={$this->course11A->id}&subject_id={$this->subjectMath->id}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res2->assertStatus(200)->assertJsonPath('data.id', $courseTpl->id);

        // 3. Resolve for Course11B (no course assignment) -> Should resolve Grade Template
        $res3 = $this->getJson("/api/v1/report-card-templates/resolve?course_id={$this->course11B->id}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res3->assertStatus(200)->assertJsonPath('data.id', $gradeTpl->id);
    }

    public function test_mass_application_with_independent_cloning_guarantees_subsequent_course_independence(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $masterTpl = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Boletín Base Grado 11', 'is_active' => true]);

        // Mass apply to 11A and 11B with independent = true
        $response = $this->postJson("/api/v1/report-card-templates/{$masterTpl->id}/mass-apply", [
            'target_scopes' => [
                ['course_id' => $this->course11A->id],
                ['course_id' => $this->course11B->id],
            ],
            'independent' => true,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(201)->assertJsonCount(2, 'data');

        // Resolve template for 11A and 11B
        $res11A = $this->getJson("/api/v1/report-card-templates/resolve?course_id={$this->course11A->id}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $tpl11AId = $res11A->json('data.id');

        $res11B = $this->getJson("/api/v1/report-card-templates/resolve?course_id={$this->course11B->id}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $tpl11BId = $res11B->json('data.id');

        // Confirm 11A and 11B resolved to DIFFERENT template IDs
        $this->assertNotEquals($tpl11AId, $tpl11BId);
        $this->assertNotEquals($masterTpl->id, $tpl11AId);

        // Now modify 11B's template
        $this->putJson("/api/v1/report-card-templates/{$tpl11BId}", [
            'name' => 'Boletín Exclusivo 11B',
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA))->assertStatus(200);

        // Confirm 11A's template name remains intact
        $res11AReread = $this->getJson("/api/v1/report-card-templates/{$tpl11AId}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $this->assertNotEquals('Boletín Exclusivo 11B', $res11AReread->json('data.name'));
    }

    public function test_cross_tenant_template_access_rejected(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $tplA = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Template School A', 'is_active' => true]);

        // User B from School B tries to access School A's template
        $response = $this->getJson("/api/v1/report-card-templates/{$tplA->id}", $this->authHeaders($this->userB, $this->schoolB, $this->campusB));

        $response->assertStatus(404);
    }

    public function test_cross_tenant_assignment_entity_rejected(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $tplA = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Template School A', 'is_active' => true]);

        // School A tries to assign template to School B's campus/course context
        RlsManager::setTenantContext($this->schoolB->id);
        $yearB = AcademicYear::create(['school_id' => $this->schoolB->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'status' => 'ACTIVE', 'is_current' => true]);
        $levelB = EducationalLevel::create(['school_id' => $this->schoolB->id, 'name' => 'Secundaria B', 'code' => 'SECB', 'level_order' => 2]);
        $gradeB = Grade::create(['school_id' => $this->schoolB->id, 'educational_level_id' => $levelB->id, 'name' => 'Grado 11° B', 'code' => 'G11B', 'grade_order' => 11]);
        $courseB = Course::create(['school_id' => $this->schoolB->id, 'campus_id' => $this->campusB->id, 'academic_year_id' => $yearB->id, 'grade_id' => $gradeB->id, 'name' => '11B-SchoolB', 'shift' => 'MAÑANA']);

        // User A tries to assign template A to Course B
        $response = $this->postJson('/api/v1/report-card-assignments', [
            'template_id' => $tplA->id,
            'course_id' => $courseB->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(422);
    }

    public function test_rbac_reports_view_and_reports_generate_permissions_enforced(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);

        // User without reports.generate tries to create template
        $userNoPerm = User::factory()->create();
        $linkNoPerm = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $userNoPerm->id, 'status' => 'ACTIVE']);
        $roleNoPerm = Role::create(['school_id' => $this->schoolA->id, 'name' => 'READONLY_ROLE', 'guard_name' => 'web']);
        $permView = Permission::where('name', 'reports.view')->first();
        $roleNoPerm->permissions()->sync([$permView->id]);
        $linkNoPerm->roles()->sync([$roleNoPerm->id => ['school_id' => $this->schoolA->id]]);

        // GET allowed
        $this->getJson('/api/v1/report-card-templates', $this->authHeaders($userNoPerm, $this->schoolA, $this->campusA))
            ->assertStatus(200);

        // POST rejected with 403 Forbidden
        $this->postJson('/api/v1/report-card-templates', [
            'name' => 'Unauthorized Template',
        ], $this->authHeaders($userNoPerm, $this->schoolA, $this->campusA))
            ->assertStatus(403);
    }
}
