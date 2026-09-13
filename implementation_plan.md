# IMPLEMENTATION PLAN
# Plataforma SaaS Educativa Multi-Tenant
## Laravel + PHP 8.3+ + PostgreSQL 16+ + React

**Versión:** 6.0  
**Estado:** Plan Maestro de Implementación Actualizado y Sincronizado  
**Tipo de documento:** Plan maestro de implementación  
**Responsabilidad:** Definir QUÉ debe construirse, el alcance funcional, la hoja de ruta por fases y las reglas arquitectónicas inmutables.

---

# 1. OBJETIVO DEL PRODUCTO

Construir una plataforma SaaS de gestión educativa multi-tenant que permita administrar múltiples colegios desde una única plataforma tecnológica.

La arquitectura permite que un nuevo colegio sea incorporado al sistema mediante configuración y creación de su tenant, sin desplegar:

- un nuevo frontend;
- un nuevo backend;
- una nueva aplicación;
- una nueva base de datos.

Todos los colegios utilizan:

- el mismo código fuente;
- el mismo frontend;
- el mismo backend;
- la misma infraestructura;
- la misma base de datos PostgreSQL.

La separación entre colegios es lógica y está protegida mediante múltiples capas de seguridad (Defensa en Profundidad).

---

# 2. MODELO CONCEPTUAL DEL SISTEMA

La jerarquía principal es:

Platform
│
├── School / Tenant (Frontera Absoluta de Seguridad)
│   │
│   ├── Campus / Sede (Subdivisión Interna de Escuela)
│   │   ├── Classrooms
│   │   ├── Courses
│   │   ├── Teachers
│   │   └── Students
│   │
│   ├── Academic Years
│   ├── Academic Periods
│   ├── Educational Levels / Grades
│   ├── Subjects
│   ├── Assessments / Competencies
│   ├── Grades (Assessment Grades & Period Final Grades)
│   ├── Attendance
│   ├── Report Cards / Templates / Snapshots / PDFs / ZIPs
│   ├── Media Files
│   └── Audit Logs
│
└── Platform Administration (SaaS Provisioning & SuperAdmin)

## 2.1 Tenant
El `school_id` representa el límite absoluto de seguridad de un colegio. Todo dato perteneciente a un colegio debe quedar aislado de cualquier otro colegio.

## 2.2 Campus / Sede
Un campus es una subdivisión interna de un colegio. Un colegio puede tener una o varias sedes. Los campus **NO** son tenants independientes. Por lo tanto, `school_id` siempre representa el tenant y `campus_id` representa una subdivisión interna del tenant. Nunca debe utilizarse `campus_id` como sustituto de `school_id`.

---

# 3. ARQUITECTURA DE SEGURIDAD Y DEFENSA EN PROFUNDIDAD

La seguridad multi-tenant funciona estrictamente por capas:

1. **Capa 1 — Autenticación**: Laravel Sanctum administra los tokens de autenticación.
2. **Capa 2 — Identificación del Tenant**: El tenant activo se determina mediante el flujo de autenticación y selección de colegio.
3. **Capa 3 — TenantContext**: La aplicación mantiene explícitamente el `school_id` activo.
4. **Capa 4 — Middleware**: Los endpoints tenant-aware validan autenticación, tenant activo, permisos RBAC y contexto de campus (`EnsureCampusScope`) cuando corresponda.
5. **Capa 5 — Services**: Los servicios de dominio trabajan dentro del contexto del tenant.
6. **Capa 6 — RBAC / Permisos**: La autorización valida permisos específicos por usuario y colegio (`tenant.permission:*`).
7. **Capa 7 — PostgreSQL RLS**: PostgreSQL RLS es la barrera final e inmutable de aislamiento (`app.current_school_id`).

## 3.1 Roles PostgreSQL

- **`app_user`**: Usuario de base de datos para peticiones HTTP, API y Queue Jobs de negocio (`NOSUPERUSER`, `NOBYPASSRLS`).
- **`app_system`**: Reservado exclusivamente para migraciones, seeders y comandos de infraestructura (`NOSUPERUSER`, `BYPASSRLS`). **Nunca** se utiliza en peticiones HTTP normales.

## 3.2 Queue Workers Multi-Tenant
Todos los Jobs de negocio asíncronos extienden `TenantAwareJob`, almacenan `$schoolId` y ejecutan `RlsManager::setTenantContext($this->schoolId)` en `handle()`, purgando el contexto en `finally`.

---

# 4. HOJA DE RUTA Y ESTADO DE IMPLEMENTACIÓN (ROADMAP & STATUS)

## RESUMEN DE ESTADO
- **Línea Base de Pruebas Verificada**: **245 tests / 786 assertions / 0 failures / 0 errors (100% PASS)**.
- **Fase 1 (Multi-Tenant Base Architecture)**: **COMPLETADA**
- **Fase 2 (Authentication & Tenant Operations)**: **COMPLETADA**
- **Fase 3 (Multi-Campus & Academic Core)**: **COMPLETADA**
- **Fase 4 (Advanced Modules & SaaS Operations)**: **EN DESARROLLO** (Asistencia, Calificaciones y Boletines COMPLETADOS hasta C3-B4).

---

## FASE 1 — ARQUITECTURA BASE MULTI-TENANT `[COMPLETADA]`
- [x] Modelo multi-tenant compartido con PostgreSQL 16+.
- [x] Políticas PostgreSQL RLS activas en tablas de negocio.
- [x] Integración de `TenantContext` y `RlsManager` en Laravel.
- [x] Claves foráneas compuestas e integridad referencial multi-tenant.

---

## FASE 2 — AUTENTICACIÓN, TENANT OPERATIONS Y RBAC `[COMPLETADA]`
- [x] Autenticación global en dos pasos (Login -> Pre-Tenant Token -> Select Tenant -> Tenant Access Token).
- [x] Operaciones de tenant: `select-tenant`, `switch-tenant`, `logout`, `my-schools`, `my-permissions`, `me`.
- [x] Sistema de Roles y Permisos (RBAC) dinámico por colegio (`roles`, `permissions`, `school_user_roles`).

---

## FASE 3 — MULTI-CAMPUS Y NÚCLEO ACADÉMICO `[COMPLETADA]`
- [x] Gestión de sedes/campus (`campuses`, `classrooms`, asignación de sede principal `set-main`).
- [x] Estructura académica: Años lectivos (`academic_years`), niveles educativos, grados, cursos (`courses`), asignaturas (`subjects`).
- [x] Gestión de profesores (`teachers`, asignación de materias `course_subject_teachers`).
- [x] Gestión de estudiantes y matrículas (`students`, `student_enrollments`, `course_enrollments`).
- [x] Traslados de estudiantes: Traslado de curso (`course transfer`) y traslado de sede (`campus transfer`), preservando historial académico.
- [x] Horarios y detección de conflictos (`schedules`, motor de conflictos docente/curso/aula/cross-campus).

---

## FASE 4 — MÓDULOS AVANZADOS, INFRAESTRUCTURA Y OPERACIONES SAAS `[EN DESARROLLO]`

### 4.1 Módulo de Asistencia (Attendance) `[COMPLETADO]`
- [x] **Bloque A1**: Registro masivo de asistencia por curso/fecha (`POST /api/v1/attendance`).
- [x] **Bloque A2**: Modificación y corrección de registros de asistencia (`PUT/PATCH /api/v1/attendance/{id}`).
- [x] **Bloque A3**: Consulta de historial de asistencia por estudiante (`GET /api/v1/students/{student}/attendance`).

---

### 4.2 Módulo de Calificaciones (Grading) `[PARCIALMENTE COMPLETADO]`
- [x] **Bloque B1**: Configuración de evaluaciones y asignación masiva (`POST /api/v1/assessments`, `GET /api/v1/courses/{course}/subjects/{subject}/assessments`).
- [x] **Bloque B2**: Ingreso y edición masiva de calificaciones por evaluación (`POST /api/v1/assessments/{assessment}/grades`).
- [x] **Bloque B3**: Consultas Read-Only de matriz de notas por curso/materia (`GET /api/v1/courses/{course}/subjects/{subject}/grades-matrix`) y notas de estudiante (`GET /api/v1/students/{student}/grades`).
- [ ] **Bloques Futuros de Calificaciones**:
  - [ ] Cálculo y consolidación de notas definitivas por período (`period_final_grades`).
  - [ ] Soporte para actividades de nivelación y recuperación académica.

---

### 4.3 Módulo de Boletines Académicos (Report Cards) `[BLOQUES C1 A C3-B4 COMPLETADOS]`

*Distinción Arquitectónica*: Se mantiene una separación estricta entre el cálculo académico, la generación de snapshots, el renderizado de plantillas, la persistencia en disco, el encolado masivo de PDFs y el empaquetado ZIP.

- [x] **Bloque C1 — Configuración y Asignación de Plantillas**:
  - Modelo `report_card_templates`, `sections`, `fields` y `report_card_assignments`.
  - Jerarquía de resolución de 6 niveles (`Course+Subject` > `Course` > `Grade+Subject` > `Grade` > `EducationalLevel` > `Default`).
  - Aplicación masiva con clonación independiente (`independent = true`).
- [x] **Bloque C2 — Motor de Generación, Snapshot y Versionado**:
  - Servicio `ReportCardEngine`: recopila notas, asistencias y observaciones sin modificar datos fuente.
  - Snapshot inmutable (`data_snapshot` JSONB).
  - Versionamiento atómico: $v1 \rightarrow v2$ (`SUPERSEDED`), preservación de `LOCKED` y `PUBLISHED`.
- [x] **Bloque C3-A — Generación Masiva Asíncrona de Boletines**:
  - `BulkReportCardService` y Job tenant-aware por curso `GenerateCourseReportCardsJob` con `Bus::batch()`.
  - Modos `only_missing` y `regenerate`. Endpoints `POST /generate-batch` (202 Accepted) y `GET /batches/{batchId}`.
- [x] **Bloque C3-B1 — Renderer PDF en Memoria**:
  - `ReportCardPdfRenderer` integrando `barryvdh/laravel-dompdf` (`v3.1.2`).
  - Renderizado 100% snapshot-driven. Endpoint `GET /report-cards/{id}/pdf` (Pure Read-Only).
- [x] **Bloque C3-B2 — Almacenamiento Persistente y Descarga Segura de PDF**:
  - `ReportCardPdfStorageService` y `MediaStorageService::storeRawMedia`.
  - Categoría `FileCategoryEnum::REPORT_CARD_PDF`. Archivos guardados en disco privado (`storage/app/private`).
  - Idempotencia por `id + version`. Endpoints `POST /report-cards/{id}/pdf` y `GET /report-cards/{id}/pdf/download`.
- [x] **Bloque C3-B3 — Generación Masiva Asíncrona de PDFs de Boletines**:
  - `GenerateCourseReportCardPdfsJob` (1 Job = 1 Curso) y `BulkReportCardPdfService`.
  - Operación exclusiva sobre `data_snapshot`. Cero llamadas a `ReportCardEngine`.
  - Endpoints `POST /generate-pdf-batch` (202 Accepted) y `GET /pdf-batches/{batchId}`.
- [x] **Bloque C3-B4 — Empaquetado ZIP Masivo Asíncrono de PDFs**:
  - `GenerateReportCardZipJob` (1 Job por ZIP) y `BulkReportCardZipService`.
  - Categoría `FileCategoryEnum::REPORT_CARD_ZIP`. Uso de `ZipArchive::addFile()` para consumo constante de RAM (~15-30 MB).
  - Modo `strict = true` (rechaza con HTTP 422 si faltan PDFs) vs modo `strict = false` (genera ZIP con manifiesto `boletines_faltantes.txt`).
  - Endpoints `POST /generate-pdf-zip` (202 Accepted), `GET /pdf-zip-batches/{batchId}` y `GET /pdf-zip-batches/{batchId}/download`.

---

## 5. ÁREAS PENDIENTES DEL PROYECTO (PENDING FUNCTIONAL SCOPE)

Las siguientes áreas funcionales forman parte del alcance global del proyecto y permanecen pendientes de implementación en bloques o fases futuras:

1. **Consolidación de Notas Definitivas de Período y Recuperaciones**:
   - Motor de cálculo y persistencia de `period_final_grades` a partir de evaluaciones.
   - Flujo de registro de nivelaciones / recuperaciones académicas.
2. **Infraestructura General de Archivos y Media**:
   - Carga y gestión general de avatars (estudiantes, profesores, acudientes) y logos institucionales vía `media_files`.
3. **Módulo de Auditoría del Sistema**:
   - Registro unificado de eventos de auditoría (`audit_logs`) para rastreo de operaciones críticas por usuario y tenant.
4. **SaaS Provisioning y Administración SuperAdmin**:
   - Panel de control de SuperAdmin para gestión del ciclo de vida de colegios (creación, suspensión, límites de planes).
   - Flujo automatizado de onboarding / provisioning de nuevos colegios.
5. **Aplicación Web Frontend (React)**:
   - Interfaz de usuario React para la plataforma, administradores escolares, docentes, estudiantes y acudientes.

---

# 6. RESUMEN DE CONTROL DE CAMBIOS Y BASELINE

- **Último Bloque Implementado y Verificado**: Phase 4 — Block C3-B4 (Empaquetado ZIP Masivo de PDFs de Boletines).
- **Resultado del Test Suite Global**: **245 tests, 786 assertions, 0 failures, 0 errors (100% OK)**.