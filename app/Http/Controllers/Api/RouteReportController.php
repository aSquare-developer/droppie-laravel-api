<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RouteReportController extends Controller
{
    public function download(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $routes = $request->user()
            ->routes()
            ->whereBetween('created_at', [
                $validated['from'] . ' 00:00:00',
                $validated['to'] . ' 23:59:59',
            ])
            ->where('distance_status', 'completed')
            ->orderBy('created_at')
            ->get();

        $totalDistanceKm = $routes->sum('distance_km');

        $pdf = Pdf::loadView('pdf.routes-report', [
            'user' => $request->user(),
            'routes' => $routes,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'totalDistanceKm' => $totalDistanceKm,
        ]);

        return $pdf->download(
            'routes-report-' . $validated['from'] . '-' . $validated['to'] . '.pdf'
        );
    }
}