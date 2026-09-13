<?php

namespace Tests\Feature;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\Subject;
use App\Domain\Grading\Models\Assessment;
use App\Domain\Grading\Models\GradingScale;
use App\Domain\Grading\Models\GradingScaleItem;
use App\Domain\Grading\Models\StudentGrade;
use App\Domain\Grading\Services\GradeCalculationEngine;
use App\Domain\Grading\Services\GradeConversionService;
use App\Domain\Student\Models\Student;
use App\Domain\Teacher\Models\Teacher;
use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\RlsManager;
use App\Policies\GradePolicy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GradingEngineTest extends TestCase
{
    protected School $school;
    protected GradeConversionService $conversionService;
    protected GradeCalculationEngine $calcEngine;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        $this->school = School::factory()->create();

        RlsManager::setTenantContext($this->school->id);

        $this->conversionService = new GradeConversionService();
        $this->calcEngine = new GradeCalculationEngine($this->conversionService);
    }

    public function test_grade_conversion_service_supports_numeric_scale(): void
    {
        $scale = GradingScale::factory()->create([
            'school_id' => $this->school->id,
            'scale_type' => 'NUMERIC',
            'min_score' => 1.0,
            'max_score' => 5.0,
            'is_default' => true,
        ]);

        GradingScaleItem::create([
            'school_id' => $this->school->id,
            'grading_scale_id' => $scale->id,
            'name' => 'Excelente',
            'min_value' => 4.6,
            'max_value' => 5.0,
            'equivalent_numeric_value' => 4.8,
            'item_order' => 1,
        ]);

        $res = $this->conversionService->convertGrade($scale, 4.8);

        $this->assertEquals(4.8, $res['equivalent_numeric_value']);
        $this->assertEquals('4.8', $res['normalized_value']);
    }

    public function test_grade_calculation_engine_computes_weighted_period_final_grade(): void
    {
        $course = Course::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $teacher = Teacher::factory()->create(['school_id' => $this->school->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $period = AcademicPeriod::factory()->create(['school_id' => $this->school->id, 'academic_year_id' => $course->academic_year_id]);

        $scale = GradingScale::factory()->create(['school_id' => $this->school->id, 'scale_type' => 'NUMERIC', 'is_default' => true]);

        // Assessment 1: 40% weight, Grade 4.0
        $a1 = Assessment::create([
            'school_id' => $this->school->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'academic_period_id' => $period->id,
            'teacher_id' => $teacher->id,
            'title' => 'Parcial 1',
            'weight_percentage' => 40.00,
        ]);

        // Assessment 2: 60% weight, Grade 5.0
        $a2 = Assessment::create([
            'school_id' => $this->school->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'academic_period_id' => $period->id,
            'teacher_id' => $teacher->id,
            'title' => 'Parcial 2',
            'weight_percentage' => 60.00,
        ]);

        StudentGrade::create([
            'school_id' => $this->school->id,
            'assessment_id' => $a1->id,
            'student_id' => $student->id,
            'entered_value' => '4.0',
            'equivalent_numeric_value' => 4.0,
        ]);

        StudentGrade::create([
            'school_id' => $this->school->id,
            'assessment_id' => $a2->id,
            'student_id' => $student->id,
            'entered_value' => '5.0',
            'equivalent_numeric_value' => 5.0,
        ]);

        // Expected final = (4.0 * 0.40) + (5.0 * 0.60) = 1.6 + 3.0 = 4.6
        $finalGrade = $this->calcEngine->calculateSubjectPeriodGrade($this->school->id, $course->id, $subject->id, $student->id, $period->id);

        $this->assertEquals(4.6, $finalGrade->numeric_score);
    }

    public function test_grade_policy_denies_editing_grades_in_closed_academic_periods(): void
    {
        $policy = new GradePolicy();
        $user = \App\Domain\User\Models\User::factory()->create();

        $course = Course::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $teacher = Teacher::factory()->create(['school_id' => $this->school->id]);
        $closedPeriod = AcademicPeriod::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $course->academic_year_id,
            'status' => 'CLOSED',
        ]);

        $assessment = Assessment::create([
            'school_id' => $this->school->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'academic_period_id' => $closedPeriod->id,
            'teacher_id' => $teacher->id,
            'title' => 'Parcial Final',
        ]);

        $studentGrade = StudentGrade::create([
            'school_id' => $this->school->id,
            'assessment_id' => $assessment->id,
            'student_id' => Student::factory()->create(['school_id' => $this->school->id])->id,
            'entered_value' => '4.5',
            'equivalent_numeric_value' => 4.5,
        ]);

        $this->assertFalse($policy->update($user, $studentGrade));
        $this->assertFalse($policy->delete($user, $studentGrade));
    }
}
