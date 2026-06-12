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

        $fromFormatted = Carbon::parse($validated['from'])->format('d.m.Y');
        $toFormatted = Carbon::parse($validated['to'])->format('d.m.Y');

        $routes = $routeReportService->getRoutesForPeriod(
            $request->user(),
            $validated['from'],
            $validated['to']
        );

        $user = $request->user();
        $routeReportService->validateTripLog($user);
        $totalDistanceKm = $routeReportService->getTotalDistance($routes);
        $tripLogRows = $routeReportService->buildTripLogRows($routes, $user->car_mileage);

        $pdf = Pdf::loadView('pdf.routes-report', [
            'user' => $user,
            'tripLogRows' => $tripLogRows,
            'from' => $fromFormatted,
            'to' => $toFormatted,
            'totalDistanceKm' => $totalDistanceKm,
        ])->setPaper('a4', 'landscape');

        $content = $pdf->output();
        $user->update([
            'car_mileage' => $routeReportService->getEndingOdometer($tripLogRows, $user->car_mileage),
        ]);

        $filename = 'routes-report-'.$fromFormatted.'-'.$toFormatted.'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
