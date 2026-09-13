# VALIDATOR_CONTEXT.md

> Memoria técnica persistente del proyecto.
>
> Este documento NO reemplaza `implementation_plan.md`.
>
> - `implementation_plan.md` = define QUÉ debemos construir y la arquitectura/alcance aprobado.
> - `VALIDATOR_CONTEXT.md` = define DÓNDE estamos actualmente, qué está implementado, qué está pendiente y qué decisiones ya fueron aprobadas.
>
> Este archivo debe mantenerse actualizado después de cada avance importante del proyecto.

---

# 0. ESTADO ACTUAL (CURRENT STATUS)

```text
ESTADO GLOBAL: PHASE 4 — IN PROGRESS (Attendance Blocks A1-A3, Grading Blocks B1-B3 & Report Cards Blocks C1-C3-B4 Completed)
SIGUIENTE PASO: Listo para continuar con los siguientes componentes de Phase 4 (ej. SaaS Provisioning, Frontend React UI).
```

---

# 1. VISIÓN DEL PRODUCTO

## 1.1 Objetivo principal

Construir una plataforma educativa SaaS Multi-Tenant que permita que múltiples colegios utilicen el mismo software sin necesidad de desplegar una aplicación independiente para cada cliente.

Todos los colegios deben utilizar:

- el mismo código Backend;
- el mismo código Frontend;
- la misma infraestructura;
- la misma base de datos PostgreSQL.

Un nuevo colegio NO requiere:

- nuevo proyecto;
- nuevo Backend;
- nuevo Frontend;
- nueva base de datos;
- nueva instalación independiente.

Crear un nuevo cliente significa crear y configurar un nuevo TENANT dentro de la misma plataforma.

---

# 2. MODELO MULTI-TENANT

## 2.1 Jerarquía principal

La jerarquía del sistema es:

PLATAFORMA
    ↓
SCHOOL / COLEGIO (TENANT)
    ↓
CAMPUS / SEDE
    ↓
USUARIOS / PROFESORES / ESTUDIANTES / CURSOS / ETC.

Ejemplo:

Colegio Santa Cecilia
├── Campus Principal
├── Campus San Isidro
└── Campus La Laguna

Los campus pertenecen al colegio.

Un campus NO es un tenant independiente.

---

## 2.2 Regla absoluta de Tenant

`school_id` es la frontera absoluta de aislamiento entre colegios.

Nunca debe utilizarse `campus_id` como sustituto de `school_id`.

Toda entidad que pertenezca a un colegio debe respetar el aislamiento por `school_id`.

---

# 3. OBJETIVO SaaS

## Estado

- [x] Arquitectura Multi-Tenant definida.
- [x] Código compartido entre tenants.
- [x] Base de datos PostgreSQL compartida.
- [x] `school_id` como frontera tenant.
- [x] PostgreSQL RLS como capa de seguridad.
- [x] TenantContext.
- [x] RlsManager.
- [x] Aislamiento mediante Foreign Keys compuestas donde corresponde.
- [ ] Flujo completo de provisioning de nuevos colegios (Fase 4).
- [ ] Panel completo de SuperAdmin para crear/configurar colegios (Fase 4).
- [ ] Configuración completa de sedes desde administración (Fase 4).
- [ ] Gestión completa del ciclo de vida de un tenant (Fase 4).
- [ ] Configuración de límites/planes por colegio, si forma parte del alcance final (Fase 4).

---

# 4. SEGURIDAD MULTI-TENANT

## 4.1 Principio

La seguridad debe implementarse mediante defensa en profundidad.

Capas:

Laravel
    ↓
Authentication
    ↓
TenantContext
    ↓
RBAC / Permissions
    ↓
Application validation
    ↓
PostgreSQL RLS
    ↓
Database constraints / Composite FKs

---

## 4.2 PostgreSQL roles

### app_user

Uso:

- HTTP requests;
- operaciones normales de aplicación;
- queue jobs que operan bajo tenant context.

Características:

- NOSUPERUSER
- NOBYPASSRLS

Nunca debe utilizarse `app_user` para saltarse RLS.

---

### app_system

Uso:

- migrations;
- seeders;
- operaciones explícitas de infraestructura;
- operaciones administrativas de sistema que requieran bypass de RLS.

Características:

- NOSUPERUSER
- BYPASSRLS

IMPORTANTE:

`app_system` NO debe ser utilizado por controllers HTTP normales.

El hecho de que un usuario sea PLATFORM_ADMIN/SUPERADMIN no significa que una petición HTTP deba utilizar `app_system`.

---

# 5. TENANT CONTEXT

## Componentes existentes

- [x] `TenantContext`
- [x] `RlsManager`
- [x] PostgreSQL session variable `app.current_school_id`
- [x] Limpieza del contexto después de requests.
- [x] Protección frente a reutilización de conexiones PDO.
- [x] Contexto tenant para operaciones relevantes.

Principio:

El tenant autenticado determina el `school_id`.

Nunca confiar únicamente en un `school_id` enviado por el cliente.

---

# 6. CAMPUS / SEDES

## 6.1 Concepto

Un colegio puede tener múltiples campus.

Ejemplo:

Colegio Santa Cecilia
├── Campus Principal
├── Campus San Isidro
└── Campus La Laguna

Cada campus funciona operacionalmente como una subdivisión del colegio.

---

## 6.2 Reglas

- `school_id` continúa siendo la frontera de seguridad.
- `campus_id` identifica una sede dentro del colegio.
- Dos campus del mismo colegio pueden tener cursos con el mismo código.
- Las entidades contextualizadas por campus deben mantener correctamente su `school_id`.
- Las relaciones entre campus y entidades relacionadas deben protegerse mediante integridad referencial cuando corresponda.

---

## 6.3 Estado

- [x] `campuses` table.
- [x] `Campus` model.
- [x] `CampusContext`.
- [x] `EnsureCampusScope`.
- [x] Campus perteneciente a un school.
- [x] Validación de campus activo.
- [x] Validación de campus contra TenantContext.
- [x] Main campus.
- [x] Unicidad de main campus.
- [x] `classrooms`.
- [x] `campus_id` en cursos.
- [x] `campus_id` en student enrollments.
- [x] `campus_id` en course subject teachers.
- [x] Integridad cross-campus.
- [x] Tests de aislamiento cross-campus.

---

# 7. X-CAMPUS-ID

`X-Campus-ID` es CONTEXTO NO CONFIABLE.

Nunca debe considerarse una frontera de seguridad.

Flujo correcto:

X-Campus-ID
    ↓
validación
    ↓
¿Existe?
    ↓
¿Pertenece al school actual?
    ↓
¿Está ACTIVE?
    ↓
CampusContext

Nunca:

X-Campus-ID
    ↓
"confío en el valor"
    ↓
acceso directo

La seguridad principal continúa siendo `school_id` + RLS.

---

# 8. AUTENTICACIÓN Y TENANT SELECTION

Flujo establecido:

1. Login global.
2. Validación de usuario/credenciales.
3. Emisión de token Pre-Tenant.
4. Selección de colegio.
5. Validación de membership.
6. Emisión de tenant access token.
7. Requests tenant-aware.
8. Validación dinámica de membership.
9. RBAC dinámico.
10. Cambio de tenant mediante nuevo token.

---

## Estado

- [x] Login.
- [x] Pre-Tenant token.
- [x] Select Tenant.
- [x] Tenant access token.
- [x] Switch Tenant.
- [x] Logout.
- [x] My Schools.
- [x] Me.
- [x] My Permissions.
- [x] Membership validation.
- [x] Sanctum.
- [x] Tenant-aware middleware.
- [x] RBAC.

IMPORTANTE:

Sanctum abilities NO sustituyen RBAC.

---

# 9. ROLES Y PERMISOS

El sistema utiliza RBAC.

Las permissions deben mantenerse separadas del concepto de Sanctum token abilities.

Permisos implementados y validados:

- `campuses.view`
- `campuses.create`
- `campuses.update`
- `classrooms.view`
- `classrooms.create`
- `academic_years.view`
- `academic_years.create`
- `academic_years.update`
- `courses.view`
- `courses.create`
- `courses.update`
- `teachers.view`
- `teachers.create`
- `teachers.update`
- `students.view`
- `students.create`
- `students.update`
- `enrollments.view`
- `enrollments.create`
- `enrollments.update`
- `schedules.view`
- `schedules.create`
- `schedules.delete`

---

# 10. INTEGRIDAD RELACIONAL

La base de datos debe proteger las relaciones tenant/campus.

Cuando corresponda utilizar:

- UNIQUE compuesto;
- Foreign Keys compuestas;
- restricciones PostgreSQL;
- RLS.

Principio:

La aplicación no debe ser la única barrera contra relaciones cross-tenant o cross-campus inválidas.

PostgreSQL debe rechazar relaciones físicamente inválidas cuando el diseño lo requiera.

---

# 11. NÚCLEO ACADÉMICO

Estructura conceptual:

School
    ↓
Campus
    ↓
Academic Year
    ↓
Educational Level
    ↓
Grade
    ↓
Course
    ↓
Subjects
    ↓
Teachers
    ↓
Students
    ↓
Enrollments

También:

Classrooms
Schedules

---

# 12. AÑOS LECTIVOS

## Estado

- [x] AcademicYear model.
- [x] AcademicYear service.
- [x] Academic year tenant isolation.
- [x] GET `/api/v1/academic-years`.
- [x] POST `/api/v1/academic-years`.
- [x] POST `/api/v1/academic-years/{id}/activate`.

---

# 13. CURSOS

## Reglas

Un curso pertenece a:

- school;
- campus;
- academic year;
- grade;
- course code.

Los códigos de curso pueden repetirse entre campus diferentes.

Ejemplo válido:

Campus Principal → 11A
Campus San Isidro → 11A

Son cursos diferentes.

---

## Estado

- [x] Course model.
- [x] `campus_id` en Course.
- [x] Unicidad contextualizada por campus.
- [x] Tests de códigos duplicados entre campus.
- [x] `CourseController`.
- [x] GET `/api/v1/courses`.
- [x] POST `/api/v1/courses`.
- [x] POST `/api/v1/courses/{id}/assign-teacher`.
- [x] Tests completos de endpoints (`CourseApiTest`: 7 passed, 21 assertions).

---

# 14. PROFESORES

Un profesor puede enseñar:

- múltiples cursos;
- múltiples asignaturas;
- múltiples campus.

Debe existir integridad correcta entre:

Teacher
→ School
→ Campus
→ Course
→ Subject

## Estado

- [x] Teacher model.
- [x] Relaciones académicas existentes según implementación.
- [x] CourseSubjectTeacher.
- [x] `campus_id` en CourseSubjectTeacher.
- [x] Asignación de profesores a materias de curso via API.

---

# 15. ESTUDIANTES

## Estado

- [x] Student model.
- [x] Student creation.
- [x] Student listing.
- [x] Tenant isolation.
- [x] Student enrollment.
- [x] Campus-aware enrollment.
- [x] Course transfer.
- [x] Campus transfer.
- [x] Historial mediante matrículas.

---

# 16. MATRÍCULAS

Existen dos conceptos relacionados:

### Student Enrollment

Relaciona al estudiante con:

- school;
- campus;
- academic year;
- grade.

### Course Enrollment

Relaciona la matrícula del estudiante con el curso.

---

## Estado de API de Matrículas

- [x] POST `/api/v1/enrollments`.
- [x] Creación atómica de `student_enrollments` + `course_enrollments`.
- [x] Validación de unicidad de matrícula `ACTIVE` por año lectivo (Migración 000057).
- [x] Tests completos (`EnrollmentApiTest`: 9 passed, 29 assertions).

---

# 17. HISTORIAL DE MATRÍCULAS

El historial NO debe destruirse durante traslados.

Ejemplo:

2026

Matrícula #1
Campus Principal
Curso 8A
STATUS = TRANSFERRED

Matrícula #2
Campus San Isidro
Curso 8B
STATUS = ACTIVE

La matrícula histórica debe permanecer.

Esto permite conservar información relacionada con:

- calificaciones;
- asistencia;
- observaciones;
- reportes;
- historial académico.

---

# 18. DECISIÓN CRÍTICA — UNICIDAD DE STUDENT_ENROLLMENTS

La restricción histórica:

UNIQUE (
    school_id,
    student_id,
    academic_year_id
)

era incompatible con traslados dentro del mismo año.

Fue corregida mediante:

`2026_01_01_000057_fix_student_enrollments_active_uniqueness.php`

Regla actual:

Como máximo UNA matrícula ACTIVE por:

- school;
- student;
- academic year.

Las matrículas históricas pueden coexistir.

Conceptualmente:

Matrícula A → TRANSFERRED
Matrícula B → ACTIVE

Permitido.

Pero:

Matrícula A → ACTIVE
Matrícula B → ACTIVE

No permitido.

NO revertir esta decisión sin revisión arquitectónica explícita.

---

# 19. TRASLADOS

Se distinguen conceptualmente:

- Course Transfer.
- Campus Transfer.
- Grade Promotion (Fase 4).
- Grade Retention (Fase 4).

---

## Course Transfer

- GET/POST `/api/v1/transfers/course`.
- Movimiento del estudiante entre cursos dentro del mismo campus.
- Preserva historial de matrículas.

---

## Campus Transfer

- POST `/api/v1/transfers/campus`.
- Movimiento del estudiante entre campus.
- Marca la matrícula anterior como `TRANSFERRED`.
- Crea la nueva matrícula `ACTIVE` en la sede destino.
- Preserva el historial académico.

---

# 20. STUDENT TRANSFER SERVICE

Existe:

`StudentTransferService`

Responsabilidades implementadas:

- course transfer;
- campus transfer;
- historial;
- actualización de matrículas;
- asignación de `enrolled_at` en `course_enrollments`.

---

# 21. CLASSROOMS

## Estado

- [x] Classroom table.
- [x] Classroom model.
- [x] Campus relationship.
- [x] Tenant integrity.
- [x] Campus integrity.
- [x] Classroom service.
- [x] GET `/api/v1/classrooms`.
- [x] POST `/api/v1/classrooms`.

---

# 22. SCHEDULES / HORARIOS

Un horario relaciona conceptualmente:

- teacher;
- course;
- subject;
- classroom;
- campus;
- day;
- start time;
- end time;
- vigencia temporal.

Detecta conflictos en tiempo real.

---

## Estado

- [x] Schedule model.
- [x] Schedule service.
- [x] Conflict engine (docente, curso, aula, cross-campus).
- [x] GET `/api/v1/schedules`.
- [x] POST `/api/v1/schedules`.
- [x] DELETE `/api/v1/schedules/{id}` (desactivación suave `status = 'INACTIVE'`).
- [x] Tests del conflict engine y endpoints (`ScheduleApiTest`: 5 passed, 14 assertions).

---

# 23. APIS COMPROBADAS DE PHASE 3

Todas las siguientes rutas HTTP están implementadas, validadas con RLS/RBAC/TenantContext y cubiertas por pruebas automatizadas:

### Auth & Tenant
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/select-tenant`
- `POST /api/v1/auth/switch-tenant`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/tenant/my-schools`
- `GET /api/v1/tenant/my-permissions`

### Campuses & Classrooms
- `GET /api/v1/campuses`
- `POST /api/v1/campuses`
- `POST /api/v1/campuses/{id}/set-main`
- `GET /api/v1/classrooms`
- `POST /api/v1/classrooms`

### Academic Core & Structure
- `GET /api/v1/academic-years`
- `POST /api/v1/academic-years`
- `POST /api/v1/academic-years/{id}/activate`
- `GET /api/v1/courses`
- `POST /api/v1/courses`
- `POST /api/v1/courses/{id}/assign-teacher`

### Students & Enrollments
- `GET /api/v1/students`
- `POST /api/v1/students`
- `POST /api/v1/enrollments`
- `POST /api/v1/transfers/course`
- `POST /api/v1/transfers/campus`

### Schedules & Timetable
- `GET /api/v1/schedules`
- `POST /api/v1/schedules`
- `DELETE /api/v1/schedules/{id}`

### Attendance (Phase 4 - Bloques A1, A2 & A3)
- `GET /api/v1/attendance`
- `POST /api/v1/attendance` (Batch recording)
- `PUT/PATCH /api/v1/attendance/{id}` (Attendance record correction/update)
- `GET /api/v1/students/{student}/attendance` (Student attendance history)

---

# 24. ESTADO DE MIGRACIONES

- Total migraciones aplicadas: 58 migraciones (`000000` → `000057`).
- Total tablas de negocio: 50 tablas objetivo.
- Migración destacada `000057_fix_student_enrollments_active_uniqueness`: Reemplaza la restricción única global por un índice único parcial (`WHERE status = 'ACTIVE' AND deleted_at IS NULL`), permitiendo matrículas históricas tras traslados.

---

# 25. PROBLEMAS IMPORTANTES YA RESUELTOS

1. **`get_current_school_id()`**: Recreada la función en esquema PostgreSQL para soportar RLS.
2. **`school_user_roles` sin `school_id`**: Solucionado en migración `000048` permitiendo RLS e integridad compuesta.
3. **`get_user_active_memberships()`**: Creada función almacenada en PostgreSQL (migración `000049`) para validación rápida de membresías.
4. **Dependencia `school_user_campuses` obsoleta**: Eliminada la referencia en `EnsureCampusScope` respetando la arquitectura de seguridad tenant (`school_id`).
5. **Columna `campus_id` faltante en modelos**: Sincronizados los modelos `Course`, `StudentEnrollment`, `CourseEnrollment` y `CourseSubjectTeacher` con `$fillable` y relaciones `belongsTo(Campus)`.
6. **Fixtures incompletos en tests de Fase 3**: Corregidos fixtures añadiendo `student_code`, `teacher_code`, `enrollment_date`, `email`, y `enrolled_at`.
7. **Restricción de unicidad rígida en `student_enrollments`**: Corregida mediante migración `000057`.
8. **APIs de Cursos faltantes**: Implementados controllers, servicios y rutas de `courses`.
9. **API de Matrícula Inicial faltante**: Implementado `EnrollmentService`, `EnrollmentController` y `POST /api/v1/enrollments`.
10. **DELETE de Schedules no expuesto**: Implementado `delete()` en `ScheduleService`, `destroy()` en `ScheduleController` y `DELETE /api/v1/schedules/{id}`.
11. **API de Asistencia (Bloques A1, A2 & A3)**: Implementado `AttendanceService` (list, batch record, update, getStudentAttendance), `AttendanceController` y rutas GET, POST, PUT/PATCH `/api/v1/attendance` y GET `/api/v1/students/{student}/attendance`.

---

# 26. TESTING Y RESULTADOS OFICIALES

## Resultado global de la suite completa (Phase 1, 2, 3, 4)

Comando ejecutado:

`php artisan test`

Resultado obtenido:

```text
PASS Tests (176 passed)

Tests:      176 passed
Assertions: 482 assertions
Failures:   0
Errors:     0
```

## Resultado de Phase 4 (Bloques A1-A3 Attendance & Bloques B1-B3 Grading)

Comando ejecutado:

`php artisan test --filter=GradingApiTest`

Resultado obtenido:

```text
PASS Tests\Feature\Phase4\GradingApiTest (51 passed)

Tests:      51 passed
Assertions: 138 assertions
Failures:   0
Errors:     0
```

Desglose de tests principales:

- **`CourseApiTest`**: 7 passed / 21 assertions / 0 failures
- **`EnrollmentApiTest`**: 9 passed / 29 assertions / 0 failures
- **`Phase3MultiCampusAcademicCoreTest`**: 7 passed / 24 assertions / 0 failures
- **`ScheduleApiTest`**: 5 passed / 14 assertions / 0 failures
- **`AttendanceApiTest` (Bloques A1, A2 & A3)**: 24 passed / 67 assertions / 0 failures
- **`GradingApiTest` (Bloques B1, B2 & B3 — Assessment Config, Mass Assignment, Batch Grade Entry & Read-Only Queries/Matrix)**: 51 passed / 138 assertions / 0 failures

---

# 27. FASE 4 — EN DESARROLLO (Bloques A1-A3 Attendance & Bloques B1-B3 Grading Completados)

Estado de componentes de Fase 4:

- [x] **Attendance / Asistencia (Bloque A1)**: `AttendanceService`, `AttendanceController`, `GET /api/v1/attendance`, `POST /api/v1/attendance` (Batch recording).
- [x] **Attendance / Asistencia (Bloque A2)**: `updateAttendance` en `AttendanceService`, `update` en `AttendanceController`, `PUT/PATCH /api/v1/attendance/{id}`.
- [x] **Attendance / Asistencia (Bloque A3)**: `getStudentAttendance` en `AttendanceService`, `studentAttendance` en `AttendanceController`, `GET /api/v1/students/{student}/attendance`.
- [x] **Grading / Calificaciones (Bloque B1)**: Configuración de evaluaciones & asignación masiva atómica (`POST /api/v1/assessments`, `GET /api/v1/courses/{course}/subjects/{subject}/assessments`), `AssessmentService`, `AssessmentController`, `GradingApiTest` (21 passed, 59 assertions).
- [x] **Grading / Calificaciones (Bloque B2)**: Ingreso/actualización masiva atómica de calificaciones por evaluación (`POST /api/v1/assessments/{assessment}/grades`), `AssessmentGradeService`, `AssessmentGradeController`, `GradingApiTest` (37 passed total, 101 assertions).
- [x] **Grading / Calificaciones (Bloque B3)**: Consultas Read-Only de matriz de calificaciones (`GET /api/v1/courses/{course}/subjects/{subject}/grades-matrix`) y notas de estudiante (`GET /api/v1/students/{student}/grades`), `GradingQueryService`, `GradingQueryController`, `GradingApiTest` (51 passed total, 138 assertions).
- [ ] **Grading / Calificaciones (Bloques futuros)**: `period_final_grades` & snapshots (cálculo automatizado y consulta matricial de definitivas por materia/periodo), soporte para recuperación/nivelación.
- [x] **Report Cards / Boletines (Bloque C1)**: Configuración y Asignación de Plantillas (`ReportCardTemplateService`, `ReportCardTemplateController`, migración `2026_01_01_000047_add_subject_id_to_report_card_assignments_table`), jerarquía de resolución de 6 niveles (`Course+Subject` > `Course` > `Grade+Subject` > `Grade` > `EducationalLevel` > `Default`), aplicación masiva con clonación independiente (`independent = true`), endpoints API CRUD, duplicación y asignación, `ReportCardTemplateApiTest` (10 passed, 33 assertions).
- [x] **Report Cards / Boletines (Bloque C2)**: Motor de generación de boletines, snapshot inmutable (`data_snapshot` JSONB), versionamiento y supersección de versiones anteriores (`SUPERSEDED`), protección de bloqueo (`LOCKED`), supersedición tras publicación (`PUBLISHED`), endpoints REST (`ReportCardController`), `ReportCardGenerationApiTest` (9 passed, 38 assertions).
- [x] **Report Cards / Boletines (Bloque C3-A)**: Generación masiva asíncrona de boletines, orquestador `BulkReportCardService`, Job tenant-aware por curso `GenerateCourseReportCardsJob`, integración con `Bus::batch()`, modalidades `course_id`, `course_ids` y `all_courses`, modos `only_missing` y `regenerate`, preservación de protección `PUBLISHED`/`LOCKED`, endpoints `POST /api/v1/report-cards/generate-batch` (202 Accepted) y `GET /api/v1/report-cards/batches/{batchId}`, `ReportCardBatchApiTest` (11 passed, 33 assertions).
- [x] **Report Cards / Boletines (Bloque C3-B1)**: Renderer PDF en memoria strictly snapshot-driven (`ReportCardPdfRenderer`, Blade `pdf.report_card`, `GET /api/v1/report-cards/{id}/pdf`, pure read-only, soporte `PUBLISHED`/`LOCKED`, `ReportCardPdfApiTest` 6 passed, 75 assertions).
- [x] **Report Cards / Boletines (Bloque C3-B3)**: Generación masiva asíncrona de PDFs de boletines (`GenerateCourseReportCardPdfsJob`, `BulkReportCardPdfService`, `POST /api/v1/report-cards/generate-pdf-batch`, `GET /api/v1/report-cards/pdf-batches/{batchId}`, `only_missing` y `regenerate`, `ReportCardPdfBatchApiTest` 9 passed, 29 assertions).
- [x] **Report Cards / Boletines (Bloque C3-B4)**: Empaquetado ZIP masivo asíncrono de PDFs de boletines (`GenerateReportCardZipJob`, `BulkReportCardZipService`, `FileCategoryEnum::REPORT_CARD_ZIP`, `POST /api/v1/report-cards/generate-pdf-zip`, `GET /api/v1/report-cards/pdf-zip-batches/{batchId}`, `GET /api/v1/report-cards/pdf-zip-batches/{batchId}/download`, `strict` mode vs manifest mode, `ReportCardPdfZipBatchApiTest` 10 passed, 41 assertions).
- [ ] **Media / Archivos**: `media_files` table, uploads, avatars, document storage.
- [ ] **Auditoría**: `audit_logs` table, event tracking.
- [ ] **Frontend React**: Interfaz de usuario para plataforma, SuperAdmin y colegios.
- [ ] **SaaS Provisioning**: Panel de SuperAdmin y flujo automatizado de onboarding de nuevos colegios.

---

# 28. ESTADO DE FASES

- **FASE 1 — MULTI-TENANT BASE**: **COMPLETADA**
- **FASE 2 — AUTH & TENANT OPERATIONS**: **COMPLETADA**
- **FASE 3 — MULTI-CAMPUS & NÚCLEO ACADÉMICO**: **COMPLETADA**
- **FASE 4 — MÓDULOS AVANZADOS, FRONTEND Y SAAS**: **EN DESARROLLO (Bloques A1-A3, B1-B3 & C1-C3-B4 Completados)**

---

# 29. DETALLE TÉCNICO DE IMPLEMENTACIÓN — PHASE 4 BLOQUE C1 (REPORT CARDS: CONFIGURACIÓN Y ASIGNACIÓN DE PLANTILLAS)

## 29.1 Resumen de Avance
En la implementación del Bloque C1 de Phase 4 para **Report Cards / Boletines**, se introdujo la infraestructura completa de administración y asignación jerárquica de plantillas de boletines:
1. **Base de Datos & Migración**:
   - `2026_01_01_000047_add_subject_id_to_report_card_assignments_table`: Agrega `subject_id` (nullable FK a `subjects`) a `report_card_assignments` e introduce el índice único condicional `uq_report_card_assignment_scope` sobre `(school_id, COALESCE(subject_id, 0), COALESCE(course_id, 0), COALESCE(grade_id, 0), COALESCE(educational_level_id, 0))`.
2. **Modelos & Dominio**:
   - `App\Domain\ReportCard\Models\ReportCardAssignment`: Actualizado para incluir `subject_id` y la relación `subject()`.
   - `App\Domain\ReportCard\Services\ReportCardEngine`: Actualizado `resolveTemplate` para implementar strictly la jerarquía de precedencia de 6 niveles.
   - `App\Domain\ReportCard\Services\ReportCardTemplateService`: Servicio del dominio para la creación transaccional de plantillas con secciones/campos dinámicos, edición, duplicación profunda (clonación estricta de secciones y campos), asignación por alcance y aplicación masiva con clonación independiente.
3. **Controlador HTTP & API Endpoints**:
   - `App\Http\Controllers\Api\V1\ReportCardTemplateController`: Implementa los 10 endpoints REST de administración y resolución.
   - `routes/api.php`: Endpoints registrados bajo la categoría B protegidos por `auth:sanctum`, `identify.tenant`, `ensure.campus` y permisos RBAC `reports.view` (lectura/resolución) y `reports.generate` (escritura/configuración/asignación).
4. **Aplicación Masiva e Independencia por Curso**:
   - La opción `independent = true` en la aplicación masiva genera una copia independiente profunda del template, secciones y campos para cada curso o materia destino (`"Nombre Base - Curso 11A"`), asegurando que modificaciones posteriores en 11B no afecten a 11A, 11C, 11D ni 11E.
5. **Verificación & Tests**:
   - `tests/Feature/Phase4/ReportCardTemplateApiTest.php`: 10 tests unitarios/feature que validan la creación, filtro, actualización, duplicación, asignación por scopes, resolución jerárquica, aplicación masiva independiente, aislamiento cross-tenant y permisos RBAC (10 passed, 33 assertions).
   - `tests/Feature/ReportCardEngineTest.php`: 3 tests pasando (11 assertions).

---

# 30. DETALLE TÉCNICO DE IMPLEMENTACIÓN — PHASE 4 BLOQUE C2 (REPORT CARDS: MOTOR DE GENERACIÓN, SNAPSHOT Y VERSIONADO)

## 30.1 Resumen de Avance
En la implementación del Bloque C2 de Phase 4 para **Report Cards / Boletines**, se desarrolló el motor de generación, construcción de snapshots históricos inmutables y versionamiento atómico de boletines:
1. **Dominio & Motor de Generación (`ReportCardEngine`)**:
   - `generateReportCard`: Transacción de base de datos (`DB::transaction`) que valida pertenencia de entidades al tenant activo (`school_id`), recopila notas finales (`period_final_grades` con ítems de escala de evaluación y detalles de escala `scale`), resumen de asistencia (`attendances` con conteo de asistencias/faltas/justificaciones) e información de observaciones docentes (`teacher_period_observations`).
   - Operación Read-Only sobre datos fuente: La generación **jamás modifica ni recalcula** notas finales, observaciones ni registros de asistencia.
2. **Snapshot Inmutable (`data_snapshot` JSONB)**:
   - Estructura JSONB completa y autocontenida que almacena colegio, año lectivo, periodo académico, datos del estudiante, curso, grado, nivel educativo, notas finales con equivalencias cualitativas y colores, métricas de asistencia, observaciones y estructura de secciones/campos de la plantilla resuelta.
   - **Inmutabilidad**: Cambios posteriores en las notas fuente o plantillas **no alteran** el snapshot de boletines ya generados.
3. **Versionado & Regeneración**:
   - Primera generación: `version = 1`, `is_latest = true`, `status = 'GENERATED'`.
   - Regeneración: Incrementa `version` a $N+1$, establece `parent_report_card_id` apuntando a la versión previa, archiva la versión anterior con `is_latest = false` y `status = 'SUPERSEDED'`, y registra `regeneration_reason`.
4. **Reglas de Bloqueo (`LOCKED`) y Publicación (`PUBLISHED`)**:
   - **`LOCKED`**: Si el boletín existente `is_latest` se encuentra en estado `LOCKED`, la regeneración es rechazada con un error de validación HTTP 422.
   - **`PUBLISHED = protegido contra regeneración directa`**: Un boletín en estado `PUBLISHED` es considerado un documento protegido (equivalente a `LOCKED` para regeneración). Cualquier intento de regeneración directa es rechazado con error de validación HTTP 422, manteniendo intactos el snapshot (`data_snapshot`), versión (`version`), estado (`PUBLISHED`), `is_latest` y `parent_report_card_id`.
5. **Controladores & API REST (`ReportCardController`)**:
   - `POST /api/v1/report-cards/generate`: Endpoint para generar/regenerar boletines (`reports.generate`).
   - `GET /api/v1/report-cards`: Endpoint para listar boletines del tenant con filtros (`reports.view`).
   - `GET /api/v1/report-cards/{id}`: Endpoint para obtener el snapshot y detalle de un boletín (`reports.view`).
   - `GET /api/v1/students/{student}/report-cards`: Endpoint para consultar el historial de boletines de un estudiante (`reports.view`).
6. **Verificación & Tests**:
   - `tests/Feature/Phase4/ReportCardGenerationApiTest.php`: 11 tests unitarios y feature que validan generación, snapshot, inmutabilidad, versionado, supersección, rechazo de regeneración en bloqueos `LOCKED` y `PUBLISHED`, aislamiento cross-tenant, permisos RBAC y la garantía read-only sobre las notas fuente (11 passed, 43 assertions).
   - **Suite Conjunta C1 + C2**: `ReportCardGenerationApiTest` + `ReportCardTemplateApiTest` + `ReportCardEngineTest` (24 passed, 90 assertions).

---

# 31. DETALLE TÉCNICO DE IMPLEMENTACIÓN — PHASE 4 BLOQUE C3-A (REPORT CARDS: GENERACIÓN MASIVA ASÍNCRONA DE BOLETINES)

## 31.1 Resumen de Avance
En la implementación del Bloque C3-A de Phase 4 para **Report Cards / Boletines**, se desarrolló el módulo de generación masiva asíncrona de boletines basado en la arquitectura de colas y batches de Laravel:

1. **Orquestador Masivo (`BulkReportCardService`)**:
   - `dispatchBatch`: Recibe la solicitud HTTP, valida que se especifique exactamente **una única modalidad de selección** (`course_id`, `course_ids` o `all_courses = true`), valida la pertenencia de cursos al tenant autenticado (`school_id`) y scope de campus (`X-Campus-ID` si aplica), resuelve los cursos válidos, crea una instancia del Job `GenerateCourseReportCardsJob` por cada curso y despacha un batch asíncrono vía `Bus::batch()`.
   - Almacena el ID del colegio (`school_id`) y metadatos en las opciones del batch (`withOption('school_id', $schoolId)`), permitiendo aislamiento y autorización segura en la consulta de estado.
2. **Job por Curso (`GenerateCourseReportCardsJob`)**:
   - Extiende de `App\Infrastructure\Tenant\Contracts\TenantAwareJob` implementando la interfaz `ShouldQueue` y el trait `Batchable`.
   - Asigna **1 Job = 1 Curso** como unidad principal de procesamiento.
   - Envoltorio de seguridad: En `handle()`, establece `RlsManager::setTenantContext($this->schoolId)` antes de procesar y garantiza la limpieza con `RlsManager::purgeTenantContext()` en el bloque `finally`.
   - **Reutilización de `ReportCardEngine`**: Para cada estudiante matriculado activo en el curso, se invoca `ReportCardEngine::generateReportCard()`, conservando la atomicidad transaccional individual por estudiante y reutilizando sin duplicidad la lógica de notas, asistencias, observaciones, snapshots y versionamiento.
3. **Modos de Generación (`mode`)**:
   - **`only_missing` (predeterminado)**: Omite el procesamiento de estudiantes que ya posean un boletín activo (`GENERATED`/`DRAFT`) en el período actual, evitando consumo innecesario.
   - **`regenerate`**: Permite la regeneración normal produciendo una nueva versión ($N+1$) y marcando la versión previa como `SUPERSEDED`.
4. **Preservación Estricta de Protección (`PUBLISHED` / `LOCKED`)**:
   - Los boletines existentes en estado `PUBLISHED` o `LOCKED` son omitidos/protegidos automáticamente sin importar el modo especificado. La excepción arrojada por `ReportCardEngine` es capturada individualmente por estudiante, no altera el registro ni destruye la ejecución del resto de estudiantes del lote.
5. **API REST & Endpoints (`ReportCardController`)**:
   - `POST /api/v1/report-cards/generate-batch`: Protegido por `reports.generate`. Despacha el batch en segundo plano y responde inmediatamente con HTTP `202 Accepted`, retornando `batch_id`, estado `pending` y `total_courses`.
   - `GET /api/v1/report-cards/batches/{batchId}`: Protegido por `reports.view`. Consulta el estado del batch en `job_batches`, verificando que `options.school_id === TenantContext::id()` para evitar fuga de información cross-tenant (retornando HTTP 404 ante intentos no autorizados).
6. **Verificación & Test Suite**:
   - `tests/Feature/Phase4/ReportCardBatchApiTest.php`: 11 tests que verifican respuestas 202 Accepted, modalidades de selección, rechazo de combinaciones ambiguas (HTTP 422), ejecución de jobs, modos `only_missing` y `regenerate`, preservación de `PUBLISHED`/`LOCKED`, aislamiento tenant en consulta de batch y validación de permisos RBAC (11 passed, 33 assertions).
   - **Suite Conjunta Módulo Report Cards (C1 + C2 + C3-A)**: 35 tests pasando, 123 aserciones (0 fallos).
   - **Suite Completa del Proyecto (`php artisan test`)**: 82 tests pasando, 266 aserciones, 0 fallos, 0 errores.

---

# 32. DETALLE TÉCNICO DE IMPLEMENTACIÓN — PHASE 4 BLOQUE C3-B1 (REPORT CARDS: RENDERER PDF DE REPORT CARDS)

## 32.1 Resumen de Avance
En la implementación del Bloque C3-B1 de Phase 4 para **Report Cards / Boletines**, se desarrolló la capacidad de renderizado PDF en memoria:

1. **Librería Pure PHP**: Se integró `barryvdh/laravel-dompdf` (`v3.1.2`), registrada en `bootstrap/providers.php`, permitiendo generar documentos PDF sin dependencias externas de Node.js o navegadores Headless.
2. **Servicio Renderer (`ReportCardPdfRenderer`)**:
   - `renderPdfFromSnapshot(array $snapshot): string`: Renderiza la vista Blade `pdf.report_card` y aplica el tamaño de papel (`letter`, `a4`) y la orientación (`portrait`, `landscape`) configurados en `template.layout_config`.
3. **Lectura Exclusiva desde Snapshot Inmutable**:
   - El PDF se genera 100% a partir de `data_snapshot` JSONB. Cero consultas a las tablas fuente de notas o asistencias durante el renderizado.
4. **Garantía Read-Only**:
   - C3-B1 es una operación estrictamente de lectura. No muta `status`, `version`, `is_latest`, `parent_report_card_id`, `data_snapshot` ni `pdf_media_file_id`. No crea registros en `media_files` ni archivos en disco.
5. **Soporte `PUBLISHED` y `LOCKED`**:
   - Permite renderizar PDFs de boletines en estado publicado o bloqueado sin alterar su estado ni arrojar errores.
6. **API Endpoint & Permisos**:
   - `GET /api/v1/report-cards/{id}/pdf`: Protegido por Sanctum, middleware tenant (`identify.tenant`, `ensure.campus`) y permiso RBAC `reports.view`. Retorna flujo binario `application/pdf`.
7. **Verificación**:
   - `tests/Feature/Phase4/ReportCardPdfApiTest.php`: 6 tests pasando (75 assertions).

---

# 33. DETALLE TÉCNICO DE IMPLEMENTACIÓN — PHASE 4 BLOQUE C3-B2 (REPORT CARDS: ALMACENAMIENTO PERSISTENTE Y DESCARGA SEGURA DE PDF)

## 33.1 Resumen de Avance
En la implementación del Bloque C3-B2 de Phase 4 para **Report Cards / Boletines**, se desarrolló el sistema de persistencia y descarga segura de PDFs en storage privado:

1. **Extensión de `MediaStorageService`**:
   - Método `storeRawMedia(...)`: Almacena flujos binarios crudos en el disco privado `local` (`storage/app/private`), utilizando la categoría `FileCategoryEnum::REPORT_CARD_PDF` (`REPORT_CARD_PDF`), asignando UUIDs únicos y registrando la entrada en `media_files`.
2. **Servicio Orquestador (`ReportCardPdfStorageService`)**:
   - `generateAndStorePdf(ReportCard $reportCard, ?int $userId = null)`: Orquesta la generación (mediante `ReportCardPdfRenderer`) y persistencia del PDF.
   - **Idempotencia y Re-uso por Versión**: Si el archivo PDF asociado a la combinación única `report_card.id + version` ya existe en el disco, se reutiliza sin duplicar registros en `media_files`.
   - **Reconstrucción Automática**: Si el archivo físico fue eliminado del disco, se reconstruye transparentemente a partir del `data_snapshot` congelado.
3. **Relación en Modelo `ReportCard`**:
   - Agregada la relación `pdfMediaFile(): BelongsTo` apuntando a `App\Domain\Document\Models\MediaFile`.
4. **Regla Estricta de Inmutabilidad Documental**:
   - El único campo modificado en `report_cards` es `pdf_media_file_id`.
   - Se mantiene 100% inmutable: `status`, `version`, `is_latest`, `parent_report_card_id`, `data_snapshot`, notas y asistencias.
   - Versiones diferentes del mismo boletín (v1 y v2) conservan archivos PDF e IDs de `MediaFile` independientes.
5. **Controladores y API REST (`ReportCardController`)**:
   - `POST /api/v1/report-cards/{id}/pdf`: Genera/persiste el PDF y asocia `pdf_media_file_id`. Requiere permiso RBAC `reports.generate`. Responde HTTP 200 con el modelo `ReportCard` y la relación `pdfMediaFile`.
   - `GET /api/v1/report-cards/{id}/pdf/download`: Permite la descarga/visualización del PDF almacenado en storage privado. Requiere permiso RBAC `reports.view`. Verifica pertenencia tenant tanto del boletín como del `MediaFile`. Retorna la respuesta binaria de archivo seguro (`application/pdf`).
6. **Soporte `PUBLISHED` y `LOCKED`**:
   - Boletines en estado `PUBLISHED` o `LOCKED` pueden almacenar y actualizar su asociación `pdf_media_file_id` sin romper la protección de bloqueo ni alterar la versión.
7. **Verificación Completa del Sistema**:
   - `tests/Feature/Phase4/ReportCardPdfStorageTest.php`: 12 tests unitarios y de integración (47 assertions).
   - **Suite Completa del Módulo Report Cards**: 53 tests pasando (243 assertions).
   - **Suite Completa del Proyecto (`vendor/bin/phpunit`)**: **226 tests, 716 assertions — OK (100% PASS)**.

---

# 34. DETALLE TÉCNICO DE IMPLEMENTACIÓN — PHASE 4 BLOQUE C3-B3 (REPORT CARDS: GENERACIÓN MASIVA ASÍNCRONA DE PDFs DE BOLETINES)

## 34.1 Resumen de Avance
En la implementación del Bloque C3-B3 de Phase 4 para **Report Cards / Boletines**, se desarrolló el sistema de generación masiva asíncrona de archivos PDF:

1. **Job Granular por Curso (`GenerateCourseReportCardPdfsJob`)**:
   - Extiende `TenantAwareJob` y utiliza `Batchable`. Unidad: **1 Job = 1 Curso**.
   - Procesa asíncronamente boletines vigentes (`is_latest = true`) leyendo exclusivamente su snapshot congelado `data_snapshot`.
   - **Cero recalculo académico**: Jamás invoca `ReportCardEngine`.
   - Modos: `only_missing` (omite boletines que ya poseen PDF válido en disco) y `regenerate` (fuerza el re-renderizado binario desde el snapshot).
2. **Orquestador (`BulkReportCardPdfService`)**:
   - Valida modalidades de selección (`course_id`, `course_ids`, `all_courses=true`), pertenencia tenant (`school_id`) y alcance de campus (`X-Campus-ID`).
   - Despacha el batch en colas vía `Bus::batch()`, guardando `school_id` en las opciones del batch para autorización cross-tenant.
3. **Endpoints & RBAC (`ReportCardController`)**:
   - `POST /api/v1/report-cards/generate-pdf-batch`: Protegido por `reports.generate`. Encola batch y responde HTTP 202 Accepted.
   - `GET /api/v1/report-cards/pdf-batches/{batchId}`: Protegido por `reports.view`. Retorna progreso del batch, empleando aislamiento tenant `school_id` (404 ante solicitudes cross-tenant).
4. **Verificación**:
   - `tests/Feature/Phase4/ReportCardPdfBatchApiTest.php`: 9 tests integrales (29 assertions) pasando.

---

# 35. DETALLE TÉCNICO DE IMPLEMENTACIÓN — PHASE 4 BLOQUE C3-B4 (REPORT CARDS: EMPAQUETADO ZIP MASIVO ASÍNCRONO DE PDFs)

## 35.1 Resumen de Avance
En la implementación del Bloque C3-B4 de Phase 4 para **Report Cards / Boletines**, se desarrolló el módulo de empaquetado masivo asíncrono en formato ZIP:

1. **Categoría de Documento**:
   - Incorporado `case REPORT_CARD_ZIP = 'REPORT_CARD_ZIP';` en `FileCategoryEnum`.
2. **Servicio Orquestador (`BulkReportCardZipService`)**:
   - Valida modalidad de selección (`course_id`, `course_ids`, `all_courses=true`), tenant context y campus scope opcional.
   - Selecciona únicamente boletines vigentes (`is_latest = true`).
   - **Evaluación de Modo Estricto (`strict`)**:
     - `strict = true`: Si falta algún PDF en la selección, rechaza el despacho con error **HTTP 422 Unprocessable Entity**.
     - `strict = false` (default): Despacha el batch empaquetando todos los PDFs válidos e incorporando un manifiesto `boletines_faltantes.txt` dentro de la raíz del ZIP si se detectan faltantes.
3. **Job Asíncrono de Empaquetado (`GenerateReportCardZipJob`)**:
   - Extiende `TenantAwareJob` e integra `Batchable`. Granularidad: **1 Job por Archivo ZIP**.
   - Utiliza `ZipArchive::addFile($physicalPath, $localZipPath)` evitando cargar binarios en arreglos PHP (consumo constante de RAM ~15-30 MB).
   - Sanitización estricta de rutas y nombres internos dentro del ZIP (`{CourseName}/boletin-{LastName}-{FirstName}-{id}-v{version}.pdf`), evitando ZipSlip y colisiones.
   - Limpieza garantizada de archivos temporales en bloque `finally`.
   - Persistencia final vía `MediaStorageService::storeRawMedia(...)` en almacenamiento privado local (`storage/app/private/tenants/{schoolId}/REPORT_CARD_ZIP/{uuid}.zip`).
4. **API Endpoints & Controladores (`ReportCardController`)**:
   - `POST /api/v1/report-cards/generate-pdf-zip`: Requiere `reports.generate`. Despacha batch y responde HTTP 202 Accepted.
   - `GET /api/v1/report-cards/pdf-zip-batches/{batchId}`: Requiere `reports.view`. Devuelve progreso, `pdf_count`, `missing_count`, `zip_media_file_id` y `download_url`.
   - `GET /api/v1/report-cards/pdf-zip-batches/{batchId}/download`: Requiere `reports.view`. Stream/download del ZIP privado como `application/zip` con `Content-Disposition: attachment`.
5. **Inmutabilidad Absoluta**:
   - El proceso de empaquetado ZIP es 100% read-only sobre los boletines y sus PDFs almacenados.
6. **Verificación Completa**:
   - `tests/Feature/Phase4/ReportCardPdfZipBatchApiTest.php`: 10 tests (41 assertions) pasando.
   - **Suite Completa del Módulo Report Cards**: **72 tests, 315 assertions — OK (100% PASS)**.
   - **Suite Completa del Proyecto (`vendor/bin/phpunit`)**: **245 tests, 786 assertions — OK (100% PASS)**.