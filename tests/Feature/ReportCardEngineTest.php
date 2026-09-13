<?php

namespace Tests\Feature;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\Course;
use App\Domain\Document\Enums\FileCategoryEnum;
use App\Domain\Document\Services\MediaStorageService;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Models\ReportCardAssignment;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\ReportCard\Services\ReportCardEngine;
use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCardEngineTest extends TestCase
{
    protected School $school;
    protected ReportCardEngine $reportEngine;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        $this->school = School::factory()->create();

        RlsManager::setTenantContext($this->school->id);
        $this->reportEngine = new ReportCardEngine();
    }

    public function test_report_card_template_resolves_hierarchically_for_specific_course(): void
    {
        $course = Course::factory()->create(['school_id' => $this->school->id]);

        $defaultTemplate = ReportCardTemplate::create([
            'school_id' => $this->school->id,
            'name' => 'Plantilla General',
            'is_active' => true,
        ]);

        $courseTemplate = ReportCardTemplate::create([
            'school_id' => $this->school->id,
            'name' => 'Plantilla Específica Curso 6A',
            'is_active' => true,
        ]);

        ReportCardAssignment::create([
            'school_id' => $this->school->id,
            'template_id' => $courseTemplate->id,
            'course_id' => $course->id,
        ]);

        $resolved = $this->reportEngine->resolveTemplate($this->school->id, $course);

        $this->assertEquals($courseTemplate->id, $resolved->id);
        $this->assertEquals('Plantilla Específica Curso 6A', $resolved->name);
    }

    public function test_regenerating_report_card_creates_new_version_and_supersedes_old(): void
    {
        $course = Course::factory()->create(['school_id' => $this->school->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $period = AcademicPeriod::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $course->academic_year_id,
        ]);

        ReportCardTemplate::create([
            'school_id' => $this->school->id,
            'name' => 'Plantilla Test',
            'is_active' => true,
        ]);

        // Version 1
        $v1 = $this->reportEngine->generateReportCard(
            $this->school->id,
            $student->id,
            $course->academic_year_id,
            $period->id,
            $course
        );

        $this->assertEquals(1, $v1->version);
        $this->assertTrue($v1->is_latest);

        // Version 2 (Regeneration)
        $v2 = $this->reportEngine->generateReportCard(
            $this->school->id,
            $student->id,
            $course->academic_year_id,
            $period->id,
            $course,
            'Corrección de nota'
        );

        $this->assertEquals(2, $v2->version);
        $this->assertTrue($v2->is_latest);

        // Refresh v1 and check superseded status
        $v1->refresh();
        $this->assertFalse($v1->is_latest);
        $this->assertEquals('SUPERSEDED', $v1->status);
    }

    public function test_media_storage_service_stores_file_with_tenant_isolation(): void
    {
        Storage::fake('local');
        $svc = new MediaStorageService();

        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $file = UploadedFile::fake()->image('avatar.jpg');

        $media = $svc->storeMedia(
            $this->school->id,
            $student,
            $file,
            FileCategoryEnum::STUDENT_AVATAR
        );

        $this->assertEquals($this->school->id, $media->school_id);
        $this->assertEquals(FileCategoryEnum::STUDENT_AVATAR, $media->file_category);
        Storage::disk('local')->assertExists($media->path);
    }
}
