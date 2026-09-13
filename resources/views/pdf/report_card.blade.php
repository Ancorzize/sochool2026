<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletín Académico - {{ $snapshot['student']['name'] ?? 'Estudiante' }}</title>
    <style>
        @page {
            margin: 25pt 30pt 25pt 30pt;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1a202c;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-container {
            width: 100%;
            border-bottom: 2px solid #2b6cb0;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 14pt;
            font-weight: bold;
            color: #2b6cb0;
            text-transform: uppercase;
        }
        .header-subtitle {
            font-size: 10pt;
            color: #4a5568;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
        }
        .info-table td {
            padding: 6px 10px;
            font-size: 9.5pt;
            border: 1px solid #e2e8f0;
        }
        .info-label {
            font-weight: bold;
            color: #4a5568;
            width: 18%;
        }
        .info-value {
            color: #1a202c;
            width: 32%;
        }
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #2d3748;
            border-bottom: 1px solid #cbd5e0;
            padding-bottom: 4px;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .grades-table th {
            background-color: #2b6cb0;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #2b6cb0;
        }
        .grades-table td {
            padding: 6px 8px;
            font-size: 9pt;
            border: 1px solid #cbd5e0;
            vertical-align: middle;
        }
        .grades-table tr:nth-child(even) td {
            background-color: #f7fafc;
        }
        .performance-badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8.5pt;
            font-weight: bold;
            border-radius: 3px;
            color: #ffffff;
            text-align: center;
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .attendance-table th, .attendance-table td {
            border: 1px solid #cbd5e0;
            padding: 5px 8px;
            font-size: 9pt;
            text-align: center;
        }
        .attendance-table th {
            background-color: #edf2f7;
            color: #2d3748;
        }
        .observations-block {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .observation-item {
            background-color: #f7fafc;
            border-left: 3px solid #4299e1;
            padding: 6px 10px;
            margin-bottom: 6px;
            font-size: 8.5pt;
        }
        .signatures-table {
            width: 100%;
            margin-top: 40px;
            page-break-inside: avoid;
            border-collapse: collapse;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 20px;
        }
        .signature-line {
            border-top: 1px solid #718096;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 9pt;
            font-weight: bold;
            color: #4a5568;
        }
        .footer-text {
            margin-top: 20px;
            font-size: 8pt;
            color: #a0aec0;
            text-align: center;
            border-top: 1px dashed #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    {{-- Header Section --}}
    <div class="header-container">
        @if (!empty($snapshot['template']['header_html']))
            {!! $snapshot['template']['header_html'] !!}
        @else
            <table class="header-table">
                <tr>
                    <td>
                        <div class="header-title">Boletín de Calificaciones</div>
                        <div class="header-subtitle">
                            Año Lectivo: {{ $snapshot['academic_year']['name'] ?? 'N/A' }} | 
                            Periodo: {{ $snapshot['academic_period']['name'] ?? 'N/A' }}
                        </div>
                    </td>
                    <td style="text-align: right; font-size: 8.5pt; color: #718096;">
                        VALIDATOR Platform
                    </td>
                </tr>
            </table>
        @endif
    </div>

    {{-- Student & Course Information --}}
    <table class="info-table">
        <tr>
            <td class="info-label">Estudiante:</td>
            <td class="info-value"><strong>{{ $snapshot['student']['name'] ?? 'N/A' }}</strong></td>
            <td class="info-label">Código:</td>
            <td class="info-value">{{ $snapshot['student']['code'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Documento:</td>
            <td class="info-value">{{ $snapshot['student']['document'] ?? 'N/A' }}</td>
            <td class="info-label">Curso:</td>
            <td class="info-value">{{ $snapshot['course']['name'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Grado:</td>
            <td class="info-value">{{ $snapshot['course']['grade']['name'] ?? 'N/A' }}</td>
            <td class="info-label">Nivel Educativo:</td>
            <td class="info-value">{{ $snapshot['course']['educational_level']['name'] ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- Grades Table --}}
    <div class="section-title">Rendimiento Académico</div>
    <table class="grades-table">
        <thead>
            <tr>
                <th style="width: 15%;">Código</th>
                <th style="width: 40%;">Asignatura / Materia</th>
                <th style="width: 15%; text-align: center;">Nota Final</th>
                <th style="width: 30%;">Desempeño / Escala</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($snapshot['grades'] ?? [] as $grade)
                <tr>
                    <td>{{ $grade['subject_code'] ?? '-' }}</td>
                    <td><strong>{{ $grade['subject_name'] ?? 'Asignatura' }}</strong></td>
                    <td style="text-align: center; font-weight: bold;">
                        {{ $grade['numeric_score'] ?? $grade['entered_value'] ?? 'N/A' }}
                        @if (!empty($grade['is_recovery']))
                            <br><small style="color: #e53e3e; font-weight: normal;">(Recup: {{ $grade['recovery_score'] }})</small>
                        @endif
                    </td>
                    <td>
                        @if (!empty($grade['performance_item']))
                            @php
                                $badgeColor = $grade['performance_item']['color'] ?? '#4a5568';
                            @endphp
                            <span class="performance-badge" style="background-color: {{ $badgeColor }};">
                                {{ $grade['performance_item']['name'] ?? $grade['performance_item']['label'] ?? '' }}
                            </span>
                            @if (!empty($grade['performance_item']['description']))
                                <div style="font-size: 8pt; color: #4a5568; margin-top: 2px;">
                                    {{ $grade['performance_item']['description'] }}
                                </div>
                            @endif
                        @else
                            <span style="color: #a0aec0;">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #a0aec0; padding: 10px;">
                        No hay calificaciones registradas para este periodo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Attendance Summary --}}
    @if (!empty($snapshot['attendance_summary']))
        <div class="section-title">Resumen de Asistencia</div>
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Total Registros</th>
                    <th>Asistencias</th>
                    <th>Inasistencias</th>
                    <th>Justificadas</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $snapshot['attendance_summary']['total_records'] ?? 0 }}</td>
                    <td style="color: #2f855a; font-weight: bold;">{{ $snapshot['attendance_summary']['present_count'] ?? 0 }}</td>
                    <td style="color: #c53030; font-weight: bold;">{{ $snapshot['attendance_summary']['absent_count'] ?? 0 }}</td>
                    <td style="color: #dd6b20;">{{ $snapshot['attendance_summary']['justified_count'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Teacher Observations --}}
    @if (!empty($snapshot['observations']))
        <div class="section-title">Observaciones</div>
        <div class="observations-block">
            @foreach ($snapshot['observations'] as $obs)
                <div class="observation-item">
                    {{ $obs['observation'] ?? '' }}
                </div>
            @endforeach
        </div>
    @endif

    {{-- Signatures Section --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-line">Rector(a) / Director(a)</div>
            </td>
            <td>
                <div class="signature-line">Director(a) de Grupo</div>
            </td>
        </tr>
    </table>

    {{-- Footer Section --}}
    <div class="footer-text">
        @if (!empty($snapshot['template']['footer_html']))
            {!! $snapshot['template']['footer_html'] !!}
        @else
            Documento generado automáticamente el {{ $snapshot['generated_at'] ?? now()->toDateTimeString() }} por la plataforma VALIDATOR.
        @endif
    </div>

</body>
</html>
