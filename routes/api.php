<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssessmentGradeController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\GradingQueryController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampusController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentTransferController;
use App\Http\Controllers\Api\V1\TenantProfileController;
use App\Http\Controllers\Api\V1\ReportCardTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Category A: Global Identity & Bootstrap Routes (Rate-limited, auth:sanctum ONLY)
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('select-tenant', [AuthController::class, 'selectTenant']);
            Route::post('switch-tenant', [AuthController::class, 'switchTenant']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('tenant')->group(function () {
        Route::get('my-schools', [TenantProfileController::class, 'mySchools']);
    });

    // Category B: Tenant-Aware Routes (auth:sanctum + identify.tenant + ensure.campus)
    Route::middleware(['auth:sanctum', 'identify.tenant', 'ensure.campus'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('tenant/my-permissions', [TenantProfileController::class, 'myPermissions']);

        // Academic Resource APIs protected by RBAC permissions
        Route::middleware('tenant.permission:students.view')->get('students', [StudentController::class, 'index']);
        Route::middleware('tenant.permission:students.create')->post('students', [StudentController::class, 'store']);

        // Multi-Campus & Classrooms APIs
        Route::middleware('tenant.permission:campuses.view')->get('campuses', [CampusController::class, 'index']);
        Route::middleware('tenant.permission:campuses.create')->post('campuses', [CampusController::class, 'store']);
        Route::middleware('tenant.permission:campuses.update')->post('campuses/{id}/set-main', [CampusController::class, 'setMain']);

        Route::middleware('tenant.permission:classrooms.view')->get('classrooms', [ClassroomController::class, 'index']);
        Route::middleware('tenant.permission:classrooms.create')->post('classrooms', [ClassroomController::class, 'store']);

        // Academic Structure APIs (Years & Courses)
        Route::middleware('tenant.permission:academic_years.view')->get('academic-years', [AcademicYearController::class, 'index']);
        Route::middleware('tenant.permission:academic_years.create')->post('academic-years', [AcademicYearController::class, 'store']);
        Route::middleware('tenant.permission:academic_years.update')->post('academic-years/{id}/activate', [AcademicYearController::class, 'activate']);

        Route::middleware('tenant.permission:courses.view')->get('courses', [CourseController::class, 'index']);
        Route::middleware('tenant.permission:courses.create')->post('courses', [CourseController::class, 'store']);
        Route::middleware('tenant.permission:courses.update')->post('courses/{id}/assign-teacher', [CourseController::class, 'assignTeacher']);

        // Schedules & Timetable APIs
        Route::middleware('tenant.permission:schedules.view')->get('schedules', [ScheduleController::class, 'index']);
        Route::middleware('tenant.permission:schedules.create')->post('schedules', [ScheduleController::class, 'store']);
        Route::middleware('tenant.permission:schedules.delete')->delete('schedules/{id}', [ScheduleController::class, 'destroy']);

        // Student Movements & Transfers APIs
        Route::middleware('tenant.permission:enrollments.create')->post('enrollments', [EnrollmentController::class, 'store']);
        Route::middleware('tenant.permission:enrollments.update')->post('transfers/course', [StudentTransferController::class, 'courseTransfer']);
        Route::middleware('tenant.permission:enrollments.update')->post('transfers/campus', [StudentTransferController::class, 'campusTransfer']);

        // Attendance APIs
        Route::middleware('tenant.permission:attendance.view')->get('attendance', [AttendanceController::class, 'index']);
        Route::middleware('tenant.permission:attendance.view')->get('students/{student}/attendance', [AttendanceController::class, 'studentAttendance']);
        Route::middleware('tenant.permission:attendance.create')->post('attendance', [AttendanceController::class, 'store']);
        Route::middleware('tenant.permission:attendance.create')->match(['put', 'patch'], 'attendance/{id}', [AttendanceController::class, 'update']);

        // Grading APIs
        Route::middleware('tenant.permission:grades.create')->post('assessments', [AssessmentController::class, 'store']);
        Route::middleware('tenant.permission:grades.create')->post('assessments/{assessment}/grades', [AssessmentGradeController::class, 'store']);
        Route::middleware('tenant.permission:grades.view')->get('courses/{course}/subjects/{subject}/assessments', [AssessmentController::class, 'indexForCourseSubject']);
        Route::middleware('tenant.permission:grades.view')->get('courses/{course}/subjects/{subject}/grades-matrix', [GradingQueryController::class, 'gradesMatrix']);
        Route::middleware('tenant.permission:grades.view')->get('students/{student}/grades', [GradingQueryController::class, 'studentGrades']);

        // Report Card Template & Assignment Configuration APIs
        Route::middleware('tenant.permission:reports.view')->get('report-card-templates', [ReportCardTemplateController::class, 'index']);
        Route::middleware('tenant.permission:reports.generate')->post('report-card-templates', [ReportCardTemplateController::class, 'store']);
        Route::middleware('tenant.permission:reports.view')->get('report-card-templates/resolve', [ReportCardTemplateController::class, 'resolve']);
        Route::middleware('tenant.permission:reports.view')->get('report-card-templates/{id}', [ReportCardTemplateController::class, 'show']);
        Route::middleware('tenant.permission:reports.generate')->match(['put', 'patch'], 'report-card-templates/{id}', [ReportCardTemplateController::class, 'update']);
        Route::middleware('tenant.permission:reports.generate')->post('report-card-templates/{id}/duplicate', [ReportCardTemplateController::class, 'duplicate']);
        Route::middleware('tenant.permission:reports.generate')->post('report-card-templates/{id}/mass-apply', [ReportCardTemplateController::class, 'massApply']);

        Route::middleware('tenant.permission:reports.view')->get('report-card-assignments', [ReportCardTemplateController::class, 'listAssignments']);
        Route::middleware('tenant.permission:reports.generate')->post('report-card-assignments', [ReportCardTemplateController::class, 'assign']);
        Route::middleware('tenant.permission:reports.generate')->delete('report-card-assignments/{id}', [ReportCardTemplateController::class, 'deleteAssignment']);

        // Report Card Generation & Query APIs
        Route::middleware('tenant.permission:reports.generate')->post('report-cards/generate', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'generate']);
        Route::middleware('tenant.permission:reports.generate')->post('report-cards/generate-batch', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'generateBatch']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards/batches/{batchId}', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'batchStatus']);
        Route::middleware('tenant.permission:reports.generate')->post('report-cards/generate-pdf-batch', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'generatePdfBatch']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards/pdf-batches/{batchId}', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'pdfBatchStatus']);
        Route::middleware('tenant.permission:reports.generate')->post('report-cards/generate-pdf-zip', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'generatePdfZip']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards/pdf-zip-batches/{batchId}', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'pdfZipBatchStatus']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards/pdf-zip-batches/{batchId}/download', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'downloadPdfZip']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'index']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards/{id}', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'show']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards/{id}/pdf', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'pdf']);
        Route::middleware('tenant.permission:reports.generate')->post('report-cards/{id}/pdf', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'generatePdf']);
        Route::middleware('tenant.permission:reports.view')->get('report-cards/{id}/pdf/download', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'downloadPdf']);
        Route::middleware('tenant.permission:reports.view')->get('students/{student}/report-cards', [\App\Http\Controllers\Api\V1\ReportCardController::class, 'studentReportCards']);
    });
});
