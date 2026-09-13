<?php

namespace App\Domain\ReportCard\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

class ReportCardPdfRenderer
{
    /**
     * Render PDF binary output strictly from data_snapshot array.
     */
    public function renderPdfFromSnapshot(array $snapshot): string
    {
        if (empty($snapshot)) {
            throw ValidationException::withMessages([
                'data_snapshot' => ['El data_snapshot del boletín está vacío o corrupto.'],
            ]);
        }

        $layoutConfig = $snapshot['template']['layout_config'] ?? [];
        $paperSize = $layoutConfig['paper_size'] ?? 'letter';
        $orientation = $layoutConfig['orientation'] ?? 'portrait';

        $pdf = Pdf::loadView('pdf.report_card', [
            'snapshot' => $snapshot,
        ])->setPaper($paperSize, $orientation);

        return $pdf->output();
    }
}
