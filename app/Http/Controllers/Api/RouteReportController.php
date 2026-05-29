<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RouteReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Carbon\Carbon;

class RouteReportController extends Controller
{
    public function download(Request $request, RouteReportService $routeReportService): Response 
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $fromFormatted = Carbon::parse($validated['from'])->format('d.m.Y');
        $toFormatted = Carbon::parse($validated['to'])->format('d.m.Y');

        $routes = $routeReportService->getRoutesForPeriod(
            $request->user(),
            $validated['from'],
            $validated['to']
        );

        $totalDistanceKm = $routeReportService->getTotalDistance($routes);

        $pdf = Pdf::loadView('pdf.routes-report', [
            'user' => $request->user(),
            'routes' => $routes,
            'from' => $fromFormatted,
            'to' => $toFormatted,
            'totalDistanceKm' => $totalDistanceKm,
        ]);

        return $pdf->download(
            'routes-report-' . $fromFormatted . '-' . $toFormatted . '.pdf'
        );
    }
}