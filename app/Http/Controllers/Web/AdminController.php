<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AdminMetricsService;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __invoke(AdminMetricsService $metrics): View
    {
        return view('admin.index', [
            'metrics' => $metrics->get(),
            'refreshMinutes' => AdminMetricsService::CACHE_MINUTES,
            'routesWarning' => AdminMetricsService::ROUTES_MONTHLY_WARNING,
        ]);
    }
}
