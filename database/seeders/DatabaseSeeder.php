<?php

namespace Database\Seeders;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicPlan;
use App\Domain\Academic\Models\AcademicPlanSubject;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\AchievementIndicator;
use App\Domain\Academic\Models\Competency;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\CourseEnrollment;
use App\Domain\Academic\Models\CourseSubjectTeacher;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Academic\Models\KnowledgeArea;
use App\Domain\Academic\Models\StudentEnrollment;
use App\Domain\Academic\Models\StudentIndicatorEvaluation;
use App\Domain\Academic\Models\Subject;
use App\Domain\Attendance\Models\Attendance;
use App\Domain\Attendance\Models\AttendanceStatus;
use App\Domain\Grading\Models\Assessment;
use App\Domain\Grading\Models\AssessmentCategory;
use App\Domain\Grading\Models\GradingScale;
use App\Domain\Grading\Models\GradingScaleAssignment;
use App\Domain\Grading\Models\GradingScaleItem;
use App\Domain\Grading\Models\PeriodFinalGrade;
use App\Domain\Grading\Models\PeriodGradeSnapshot;
use App\Domain\Grading\Models\StudentGrade;
use App\Domain\Grading\Services\GradeCalculationEngine;
use App\Domain\Grading\Services\GradeConversionService;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Models\ReportCardAssignment;
use App\Domain\ReportCard\Models\ReportCardField;
use App\Domain\ReportCard\Models\ReportCardSection;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\ReportCard\Models\TeacherPeriodObservation;
use App\Domain\ReportCard\Services\ReportCardEngine;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentGuardian;
use App\Domain\Teacher\Models\Teacher;
use App\Domain\Tenant\Models\School;
use App\Domain\Tenant\Models\SchoolBranding;
use App\Domain\Tenant\Models\SchoolSetting;
use App\Domain\User\Models\Permission;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Execute seeding using app_system bypass RLS
        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");

        // 1. Create Global Platform Admin
        $platformAdmin = User::updateOrCreate(
            ['email' => 'admin@platform.com'],
            [
                'uuid' => (string) Str::uuid(),
                'first_name' => 'SuperAdmin',
                'last_name' => 'Plataforma',
                'phone' => '3001234567',
                'password' => Hash::make('PlatformAdmin2026!'),
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ]
        );

        // 2. Create Permissions
        $permissions = [
            'students.view' => 'Ver estudiantes',
            'students.create' => 'Crear estudiantes',
            'students.update' => 'Editar estudiantes',
            'students.delete' => 'Eliminar estudiantes',
            'teachers.view' => 'Ver profesores',
            'teachers.create' => 'Crear profesores',
            'teachers.update' => 'Editar profesores',
            'grades.view' => 'Ver calificaciones',
            'grades.create' => 'Ingresar calificaciones',
            'grades.update' => 'Modificar calificaciones',
            'attendance.view' => 'Ver asistencia',
            'attendance.create' => 'Registrar asistencia',
            'reports.view' => 'Ver boletines',
            'reports.generate' => 'Generar boletines',
        ];

        $permissionModels = [];
        foreach ($permissions as $key => $desc) {
            $permissionModels[$key] = Permission::updateOrCreate(
                ['name' => $key],
                ['guard_name' => 'web', 'module' => explode('.', $key)[0], 'description' => $desc]
            );
        }

        // -------------------------------------------------------------
        // SEED COLEGIO 1: Colegio San José (Escala Numérica 1.0 - 5.0)
        // -------------------------------------------------------------
        $this->seedSchoolSanJose($permissionModels);

        // -------------------------------------------------------------
        // SEED COLEGIO 2: Colegio La Salle (Escala Cualitativa)
        // -------------------------------------------------------------
        $this->seedSchoolLaSalle($permissionModels);

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
    }

    private function seedSchoolSanJose(array $permissions): void
    {
        $school = School::updateOrCreate(
            ['code' => 'SAN-JOSE'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Colegio San José',
                'legal_name' => 'Colegio San José de Bogotá S.A.S.',
                'tax_identifier' => '900.123.456-1',
                'slug' => 'colegio-san-jose',
                'email' => 'contacto@sanjose.edu.co',
                'phone' => '601-5551234',
                'address' => 'Calle 100 # 15-20',
                'city' => 'Bogotá',
                'state' => 'Cundinamarca',
                'country' => 'Colombia',
                'status' => 'ACTIVE',
            ]
        );

        RlsManager::setTenantContext($school->id);

        SchoolBranding::updateOrCreate(
            ['school_id' => $school->id],
            [
                'display_name' => 'Colegio San José',
                'short_name' => 'San José',
                'primary_color' => '#1E3A8A',
                'secondary_color' => '#0284C7',
                'accent_color' => '#F59E0B',
                'login_message' => 'Bienvenido al Portal Académico del Colegio San José',
                'welcome_text' => 'Formando líderes del mañana',
                'theme' => 'light',
            ]
        );

        SchoolSetting::updateOrCreate(
            ['school_id' => $school->id],
            [
                'date_format' => 'd/m/Y',
                'time_format' => 'h:i A',
                'number_format' => '2,.,',
                'locale' => 'es',
            ]
        );

        // School Admin User
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@sanjose.edu.co'],
            [
                'uuid' => (string) Str::uuid(),
                'first_name' => 'Carlos',
                'last_name' => 'Ríos',
                'password' => Hash::make('SanJoseAdmin123!'),
                'status' => 'ACTIVE',
            ]
        );

        $schoolAdminLink = SchoolUser::updateOrCreate(
            ['school_id' => $school->id, 'user_id' => $adminUser->id],
            ['status' => 'ACTIVE']
        );

        $adminRole = Role::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'SCHOOL_ADMIN'],
            ['guard_name' => 'web', 'description' => 'Administrador del Colegio', 'is_system' => true]
        );
        $adminRole->permissions()->sync(collect($permissions)->pluck('id'));
        $schoolAdminLink->roles()->sync([$adminRole->id => ['school_id' => $school->id]]);

        // Academic Year & Periods
        $academicYear = AcademicYear::updateOrCreate(
            ['school_id' => $school->id, 'name' => '2026'],
            ['start_date' => '2026-01-15', 'end_date' => '2026-11-30', 'status' => 'ACTIVE', 'is_current' => true]
        );

        $p1 = AcademicPeriod::updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'period_order' => 1],
            ['name' => 'Periodo 1', 'weight_percentage' => 25.00, 'start_date' => '2026-01-15', 'end_date' => '2026-04-10', 'status' => 'OPEN']
        );
        $p2 = AcademicPeriod::updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'period_order' => 2],
            ['name' => 'Periodo 2', 'weight_percentage' => 25.00, 'start_date' => '2026-04-11', 'end_date' => '2026-06-25', 'status' => 'OPEN']
        );

        // Educational Levels & Grades
        $levelPrimaria = EducationalLevel::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Primaria'],
            ['code' => 'PRI', 'level_order' => 1]
        );
        $levelSecundaria = EducationalLevel::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Secundaria'],
            ['code' => 'SEC', 'level_order' => 2]
        );

        $grade5 = Grade::updateOrCreate(
            ['school_id' => $school->id, 'educational_level_id' => $levelPrimaria->id, 'name' => 'Grado 5°'],
            ['code' => 'G-5', 'grade_order' => 5]
        );
        $grade6 = Grade::updateOrCreate(
            ['school_id' => $school->id, 'educational_level_id' => $levelSecundaria->id, 'name' => 'Grado 6°'],
            ['code' => 'G-6', 'grade_order' => 6]
        );

        // Teachers
        $teachers = [];
        for ($i = 1; $i <= 3; $i++) {
            $tUser = User::updateOrCreate(
                ['email' => "docente{$i}@sanjose.edu.co"],
                [
                    'uuid' => (string) Str::uuid(),
                    'first_name' => "Profesor{$i}",
                    'last_name' => "SanJose",
                    'password' => Hash::make('TeacherPass123!'),
                ]
            );
            SchoolUser::updateOrCreate(['school_id' => $school->id, 'user_id' => $tUser->id]);
            $teachers[] = Teacher::updateOrCreate(
                ['school_id' => $school->id, 'teacher_code' => "DOC-SJ-00{$i}"],
                [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $tUser->id,
                    'document_type' => 'CC',
                    'document_number' => "101000000{$i}",
                    'first_name' => "Profesor{$i}",
                    'last_name' => 'SanJose',
                    'email' => "docente{$i}@sanjose.edu.co",
                    'specialty' => $i === 1 ? 'Matemáticas' : ($i === 2 ? 'Español' : 'Ciencias'),
                ]
            );
        }

        // Course
        $course6A = Course::updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => '6-A'],
            ['grade_id' => $grade6->id, 'director_teacher_id' => $teachers[0]->id, 'shift' => 'MAÑANA']
        );

        // Subjects & Knowledge Areas
        $areaMath = KnowledgeArea::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Matemáticas'],
            ['code' => 'MAT', 'area_order' => 1]
        );
        $subjectMath = Subject::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Matemáticas'],
            ['knowledge_area_id' => $areaMath->id, 'code' => 'MAT-6', 'color' => '#2563EB']
        );

        CourseSubjectTeacher::updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course6A->id, 'subject_id' => $subjectMath->id],
            ['teacher_id' => $teachers[0]->id, 'hours_per_week' => 4]
        );

        // NUMERIC GRADING SCALE (1.0 - 5.0)
        $numericScale = GradingScale::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Escala Numérica Institucional (1.0 - 5.0)'],
            [
                'scale_type' => 'NUMERIC',
                'min_score' => 1.00,
                'max_score' => 5.00,
                'passing_score' => 3.00,
                'decimal_places' => 1,
                'rounding_rule' => 'HALF_UP',
                'is_default' => true,
            ]
        );

        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $numericScale->id, 'name' => 'Bajo'], ['min_value' => 1.00, 'max_value' => 2.90, 'equivalent_numeric_value' => 2.00, 'color' => '#EF4444', 'item_order' => 1]);
        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $numericScale->id, 'name' => 'Básico'], ['min_value' => 3.00, 'max_value' => 3.90, 'equivalent_numeric_value' => 3.50, 'color' => '#F59E0B', 'item_order' => 2]);
        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $numericScale->id, 'name' => 'Alto'], ['min_value' => 4.00, 'max_value' => 4.50, 'equivalent_numeric_value' => 4.20, 'color' => '#3B82F6', 'item_order' => 3]);
        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $numericScale->id, 'name' => 'Excelente'], ['min_value' => 4.60, 'max_value' => 5.00, 'equivalent_numeric_value' => 4.80, 'color' => '#10B981', 'item_order' => 4]);

        GradingScaleAssignment::updateOrCreate(
            ['school_id' => $school->id, 'grading_scale_id' => $numericScale->id],
            ['priority_score' => 0]
        );

        // Attendance Statuses
        $attPresent = AttendanceStatus::updateOrCreate(['school_id' => $school->id, 'code' => 'PRESENT'], ['name' => 'Presente', 'color' => '#10B981', 'status_order' => 1]);
        $attAbsent = AttendanceStatus::updateOrCreate(['school_id' => $school->id, 'code' => 'ABSENT'], ['name' => 'Ausente Injustificado', 'is_absence' => true, 'color' => '#EF4444', 'status_order' => 2]);

        // Students & Enrollments
        for ($s = 1; $s <= 10; $s++) {
            $student = Student::updateOrCreate(
                ['school_id' => $school->id, 'student_code' => "EST-SJ-00{$s}"],
                [
                    'uuid' => (string) Str::uuid(),
                    'document_type' => 'TI',
                    'document_number' => "109000000{$s}",
                    'first_name' => "Estudiante{$s}",
                    'last_name' => 'SanJose',
                    'gender' => $s % 2 === 0 ? 'F' : 'M',
                    'birth_date' => '2013-05-10',
                    'academic_status' => 'ACTIVE',
                ]
            );

            $stEnrollment = StudentEnrollment::updateOrCreate(
                ['school_id' => $school->id, 'student_id' => $student->id, 'academic_year_id' => $academicYear->id],
                ['grade_id' => $grade6->id, 'enrollment_date' => '2026-01-15', 'status' => 'ENROLLED']
            );

            CourseEnrollment::updateOrCreate(
                ['school_id' => $school->id, 'student_enrollment_id' => $stEnrollment->id, 'course_id' => $course6A->id],
                ['enrolled_at' => now(), 'status' => 'ACTIVE']
            );

            // Attendance entry
            Attendance::updateOrCreate(
                ['school_id' => $school->id, 'course_id' => $course6A->id, 'student_id' => $student->id, 'date' => '2026-02-01'],
                ['attendance_status_id' => $attPresent->id]
            );
        }

        // Assessments (Different weights: 20%, 30%, 50%)
        $a1 = Assessment::updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course6A->id, 'subject_id' => $subjectMath->id, 'academic_period_id' => $p1->id, 'title' => 'Tarea 1: Fracciones'],
            ['teacher_id' => $teachers[0]->id, 'weight_percentage' => 20.00, 'max_score' => 5.00]
        );
        $a2 = Assessment::updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course6A->id, 'subject_id' => $subjectMath->id, 'academic_period_id' => $p1->id, 'title' => 'Quiz 1: Álgebra Básica'],
            ['teacher_id' => $teachers[0]->id, 'weight_percentage' => 30.00, 'max_score' => 5.00]
        );
        $a3 = Assessment::updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course6A->id, 'subject_id' => $subjectMath->id, 'academic_period_id' => $p1->id, 'title' => 'Examen Parcial 1'],
            ['teacher_id' => $teachers[0]->id, 'weight_percentage' => 50.00, 'max_score' => 5.00]
        );

        $students = Student::where('school_id', $school->id)->get();
        $conversionSvc = new GradeConversionService();
        $calcEngine = new GradeCalculationEngine($conversionSvc);

        foreach ($students as $idx => $st) {
            $score1 = min(5.0, 3.5 + ($idx * 0.1));
            $score2 = min(5.0, 4.0 + ($idx * 0.1));
            $score3 = min(5.0, 3.8 + ($idx * 0.1));

            foreach ([[$a1, $score1], [$a2, $score2], [$a3, $score3]] as [$ass, $sc]) {
                $c = $conversionSvc->convertGrade($numericScale, $sc);
                StudentGrade::updateOrCreate(
                    ['school_id' => $school->id, 'assessment_id' => $ass->id, 'student_id' => $st->id],
                    [
                        'entered_value' => (string)$sc,
                        'normalized_value' => $c['normalized_value'],
                        'equivalent_numeric_value' => $c['equivalent_numeric_value'],
                        'grading_scale_item_id' => $c['grading_scale_item_id'],
                        'display_value' => $c['display_value'],
                    ]
                );
            }

            // Calculate Period Final Grade & Snapshot
            $calcEngine->calculateSubjectPeriodGrade($school->id, $course6A->id, $subjectMath->id, $st->id, $p1->id);
            $calcEngine->createPeriodGradeSnapshot($school->id, $course6A->id, $st->id, $p1->id);
        }

        // Report Card Template
        $template = ReportCardTemplate::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Boletín Estándar San José'],
            ['description' => 'Plantilla oficial de calificaciones', 'is_active' => true]
        );

        ReportCardAssignment::updateOrCreate(
            ['school_id' => $school->id, 'template_id' => $template->id],
            ['course_id' => $course6A->id]
        );

        $reportEngine = new ReportCardEngine();
        foreach ($students as $st) {
            $reportEngine->generateReportCard($school->id, $st->id, $academicYear->id, $p1->id, $course6A);
        }
    }

    private function seedSchoolLaSalle(array $permissions): void
    {
        $school = School::updateOrCreate(
            ['code' => 'LA-SALLE'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Colegio La Salle',
                'legal_name' => 'Colegio La Salle Medellín S.A.S.',
                'tax_identifier' => '800.987.654-2',
                'slug' => 'colegio-la-salle',
                'email' => 'info@lasalle.edu.co',
                'phone' => '604-4449876',
                'address' => 'Carrera 43A # 1-50',
                'city' => 'Medellín',
                'state' => 'Antioquia',
                'country' => 'Colombia',
                'status' => 'ACTIVE',
            ]
        );

        RlsManager::setTenantContext($school->id);

        SchoolBranding::updateOrCreate(
            ['school_id' => $school->id],
            [
                'display_name' => 'Colegio La Salle',
                'short_name' => 'La Salle',
                'primary_color' => '#15803D',
                'secondary_color' => '#16A34A',
                'accent_color' => '#EAB308',
                'login_message' => 'Portal Institucional Colegio La Salle',
                'welcome_text' => 'Excelencia en la formación integral',
                'theme' => 'light',
            ]
        );

        SchoolSetting::updateOrCreate(
            ['school_id' => $school->id],
            ['date_format' => 'Y-m-d', 'locale' => 'es']
        );

        // School Admin User
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@lasalle.edu.co'],
            [
                'uuid' => (string) Str::uuid(),
                'first_name' => 'Mariana',
                'last_name' => 'Gómez',
                'password' => Hash::make('LaSalleAdmin123!'),
                'status' => 'ACTIVE',
            ]
        );

        $schoolAdminLink = SchoolUser::updateOrCreate(
            ['school_id' => $school->id, 'user_id' => $adminUser->id],
            ['status' => 'ACTIVE']
        );

        $adminRole = Role::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'SCHOOL_ADMIN'],
            ['guard_name' => 'web', 'description' => 'Administrador del Colegio', 'is_system' => true]
        );
        $adminRole->permissions()->sync(collect($permissions)->pluck('id'));
        $schoolAdminLink->roles()->sync([$adminRole->id => ['school_id' => $school->id]]);

        // Academic Year & Periods
        $academicYear = AcademicYear::updateOrCreate(
            ['school_id' => $school->id, 'name' => '2026'],
            ['start_date' => '2026-01-20', 'end_date' => '2026-11-25', 'status' => 'ACTIVE', 'is_current' => true]
        );

        $p1 = AcademicPeriod::updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'period_order' => 1],
            ['name' => 'Periodo I', 'weight_percentage' => 25.00, 'start_date' => '2026-01-20', 'end_date' => '2026-04-05', 'status' => 'OPEN']
        );

        $levelSecundaria = EducationalLevel::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Secundaria'],
            ['code' => 'SEC', 'level_order' => 2]
        );

        $grade7 = Grade::updateOrCreate(
            ['school_id' => $school->id, 'educational_level_id' => $levelSecundaria->id, 'name' => 'Grado 7°'],
            ['code' => 'G-7', 'grade_order' => 7]
        );

        // Teacher
        $tUser = User::updateOrCreate(
            ['email' => 'docente1@lasalle.edu.co'],
            ['uuid' => (string) Str::uuid(), 'first_name' => 'Profesor1', 'last_name' => 'LaSalle', 'password' => Hash::make('TeacherPass123!')]
        );
        SchoolUser::updateOrCreate(['school_id' => $school->id, 'user_id' => $tUser->id]);
        $teacher = Teacher::updateOrCreate(
            ['school_id' => $school->id, 'teacher_code' => 'DOC-LS-001'],
            ['uuid' => (string) Str::uuid(), 'user_id' => $tUser->id, 'document_type' => 'CC', 'document_number' => '7010000001', 'first_name' => 'Profesor1', 'last_name' => 'LaSalle', 'email' => 'docente1@lasalle.edu.co', 'specialty' => 'Lengua Castellana']
        );

        // Course
        $course7B = Course::updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => '7-B'],
            ['grade_id' => $grade7->id, 'director_teacher_id' => $teacher->id, 'shift' => 'MAÑANA']
        );

        $subjectSpanish = Subject::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Español y Literatura'],
            ['code' => 'ESP-7', 'color' => '#10B981']
        );

        CourseSubjectTeacher::updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course7B->id, 'subject_id' => $subjectSpanish->id],
            ['teacher_id' => $teacher->id, 'hours_per_week' => 5]
        );

        // QUALITATIVE GRADING SCALE (Deficiente -> Excelente)
        $qualitativeScale = GradingScale::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Escala Cualitativa La Salle'],
            [
                'scale_type' => 'QUALITATIVE',
                'min_score' => 1.00,
                'max_score' => 5.00,
                'passing_score' => 3.00,
                'is_default' => true,
            ]
        );

        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $qualitativeScale->id, 'name' => 'Deficiente'], ['equivalent_numeric_value' => 1.50, 'color' => '#DC2626', 'item_order' => 1]);
        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $qualitativeScale->id, 'name' => 'Insuficiente'], ['equivalent_numeric_value' => 2.50, 'color' => '#F97316', 'item_order' => 2]);
        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $qualitativeScale->id, 'name' => 'Aceptable'], ['equivalent_numeric_value' => 3.50, 'color' => '#EAB308', 'item_order' => 3]);
        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $qualitativeScale->id, 'name' => 'Sobresaliente'], ['equivalent_numeric_value' => 4.30, 'color' => '#2563EB', 'item_order' => 4]);
        GradingScaleItem::updateOrCreate(['school_id' => $school->id, 'grading_scale_id' => $qualitativeScale->id, 'name' => 'Excelente'], ['equivalent_numeric_value' => 4.80, 'color' => '#16A34A', 'item_order' => 5]);

        GradingScaleAssignment::updateOrCreate(
            ['school_id' => $school->id, 'grading_scale_id' => $qualitativeScale->id],
            ['priority_score' => 0]
        );

        // Competencies & Indicators
        $comp1 = Competency::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Comprensión Lectora y Análisis Crítico'],
            ['subject_id' => $subjectSpanish->id, 'educational_level_id' => $levelSecundaria->id]
        );
        $ind1 = AchievementIndicator::updateOrCreate(
            ['school_id' => $school->id, 'competency_id' => $comp1->id, 'academic_period_id' => $p1->id],
            ['description' => 'Identifica la estructura narrativa e intención del autor en textos complejos.']
        );

        // Students
        for ($s = 1; $s <= 5; $s++) {
            $student = Student::updateOrCreate(
                ['school_id' => $school->id, 'student_code' => "EST-LS-00{$s}"],
                [
                    'uuid' => (string) Str::uuid(),
                    'document_type' => 'TI',
                    'document_number' => "108000000{$s}",
                    'first_name' => "Estudiante{$s}",
                    'last_name' => 'LaSalle',
                    'gender' => $s % 2 === 0 ? 'F' : 'M',
                    'birth_date' => '2012-08-15',
                    'academic_status' => 'ACTIVE',
                ]
            );

            $stEnrollment = StudentEnrollment::updateOrCreate(
                ['school_id' => $school->id, 'student_id' => $student->id, 'academic_year_id' => $academicYear->id],
                ['grade_id' => $grade7->id, 'enrollment_date' => '2026-01-20', 'status' => 'ENROLLED']
            );

            CourseEnrollment::updateOrCreate(
                ['school_id' => $school->id, 'student_enrollment_id' => $stEnrollment->id, 'course_id' => $course7B->id],
                ['enrolled_at' => now(), 'status' => 'ACTIVE']
            );

            // Qualitative Grade
            $evalLabel = $s === 1 ? 'Excelente' : ($s === 2 ? 'Sobresaliente' : 'Aceptable');
            StudentIndicatorEvaluation::updateOrCreate(
                ['school_id' => $school->id, 'student_id' => $student->id, 'achievement_indicator_id' => $ind1->id, 'academic_period_id' => $p1->id],
                ['qualitative_evaluation' => "Desempeño {$evalLabel} en el periodo I."]
            );
        }

        // Qualitative Report Card Template
        $templateLS = ReportCardTemplate::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Boletín Cualitativo La Salle'],
            ['description' => 'Plantilla cualitativa por desempeño', 'is_active' => true]
        );

        ReportCardAssignment::updateOrCreate(
            ['school_id' => $school->id, 'template_id' => $templateLS->id],
            ['course_id' => $course7B->id]
        );
    }
}
