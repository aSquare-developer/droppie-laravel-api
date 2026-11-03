<?php

namespace App\Services;

use App\Models\TripReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class TripReportPdf
{
    /**
     * Render a stored trip report as a PDF download. Reports are immutable snapshots,
     * so rendering one again produces the same document.
     */
    public function download(TripReport $report): Response
    {
        $from = $report->period_from->format('d.m.Y');
        $to = $report->period_to->format('d.m.Y');

        $pdf = Pdf::loadView('pdf.routes-report', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="routes-report-'.$from.'-'.$to.'.pdf"',
        ]);
    }
}
