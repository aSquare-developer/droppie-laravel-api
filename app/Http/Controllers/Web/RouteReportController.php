<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TripReport;
use App\Services\RouteReportService;
use App\Services\TripReportPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RouteReportController extends Controller
{
    public function index(Request $request, RouteReportService $routeReportService): View
    {
        $user = $request->user();
        $vehicle = $user->activeVehicle()->first();
        $latest = $vehicle ? $routeReportService->latestReport($vehicle->id) : null;

        // A new report continues after the latest one, so suggest that period.
        $from = $latest?->period_to->copy()->addDay() ?? now()->subDays(30);
        $to = now()->max($from);

        return view('reports.index', [
            'reports' => $user
                ->tripReports()
                ->latest('generated_at')
                ->latest('id')
                ->paginate(10),
            'deletableReportIds' => $user->vehicles()
                ->pluck('id')
                ->map(fn (int $vehicleId): ?int => $routeReportService->latestReport($vehicleId)?->id)
                ->filter()
                ->values()
                ->all(),
            'latestReport' => $latest,
            'reportFrom' => old('from', $from->toDateString()),
            'reportTo' => old('to', $to->toDateString()),
        ]);
    }

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

    /**
     * Remove the latest report and give its start reading back to the profile mileage.
     */
    public function destroy(TripReport $report, RouteReportService $routeReportService): RedirectResponse
    {
        $routeReportService->deleteReport($report);

        return redirect()
            ->route('reports')
            ->with('success', 'Report deleted. Its start reading is back in the profile, so the period can be generated again.');
    }

    /**
     * Download a report generated earlier, without creating a new one or touching the odometer.
     */
    public function show(TripReport $report, TripReportPdf $pdf): Response
    {
        return $pdf->download($report);
    }
}
