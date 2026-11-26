<?php

namespace App\Services;

use App\Models\TripReport;
use Fagus\Laravel\Facades\Fagus;
use Illuminate\Http\Response;

class TripReportPdf
{
    /**
     * Render a stored trip report as a PDF download. Reports are immutable snapshots,
     * so rendering one again produces the same document. Page size, margins and the
     * page numbers come from the @page rules of the view.
     */
    public function download(TripReport $report): Response
    {
        $from = $report->period_from->format('d.m.Y');
        $to = $report->period_to->format('d.m.Y');

        $pdf = Fagus::view('pdf.routes-report', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ])->render();

        // The response is built here to keep the exact headers clients already know.
        return response($pdf->bytes(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="routes-report-'.$from.'-'.$to.'.pdf"',
        ]);
    }
}
