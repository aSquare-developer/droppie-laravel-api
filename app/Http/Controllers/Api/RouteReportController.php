<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RouteReportService;
use App\Services\TripReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RouteReportController extends Controller
{
    public function download(Request $request, RouteReportService $routeReportService, TripReportPdf $pdf): Response
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

        return $pdf->download($report);
    }
}
