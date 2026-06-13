<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RouteReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RouteReportController extends Controller
{
    public function download(Request $request, RouteReportService $routeReportService): Response
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $report = $routeReportService->getOrCreateReport(
            $request->user(),
            $validated['from'],
            $validated['to']
        );

        $fromFormatted = Carbon::parse($report->period_from)->format('d.m.Y');
        $toFormatted = Carbon::parse($report->period_to)->format('d.m.Y');

        $pdf = Pdf::loadView('pdf.routes-report', [
            'report' => $report,
            'from' => $fromFormatted,
            'to' => $toFormatted,
        ])->setPaper('a4', 'landscape');

        $content = $pdf->output();

        $filename = 'routes-report-'.$fromFormatted.'-'.$toFormatted.'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
