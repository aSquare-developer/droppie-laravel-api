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
    public function index(Request $request): View
    {
        return view('reports.index', [
            'reports' => $request->user()
                ->tripReports()
                ->latest('generated_at')
                ->latest('id')
                ->paginate(10),
            'reportFrom' => old('from', now()->subDays(30)->toDateString()),
            'reportTo' => old('to', now()->toDateString()),
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
     * Remove a stored report so the same period can be generated again with current data.
     * The vehicle mileage does not depend on reports, so nothing else changes.
     */
    public function destroy(TripReport $report): RedirectResponse
    {
        $report->delete();

        return redirect()
            ->route('reports')
            ->with('success', 'Report deleted. You can generate it again for the same period.');
    }

    /**
     * Download a report generated earlier, without creating a new one or touching the odometer.
     */
    public function show(TripReport $report, TripReportPdf $pdf): Response
    {
        return $pdf->download($report);
    }
}
