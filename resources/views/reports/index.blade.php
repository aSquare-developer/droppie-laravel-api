@extends('layouts.workspace')

@section('title', 'Reports - Droppie')

@section('content')
    @php
        $kmLabel = fn ($value) => number_format((float) $value, 1, '.', ',').' km';
    @endphp

    <section class="workspace">
        <aside class="control-panel">
            <section class="panel-section report-section">
                <div class="section-title">
                    <div>
                        <h2>New PDF report</h2>
                        <p>Completed trips of the active vehicle in the selected period. The report starts from the mileage in your profile.</p>
                        @if ($latestReport)
                            <p>Continues after the latest report ({{ $latestReport->period_from->format('d.m.Y') }} – {{ $latestReport->period_to->format('d.m.Y') }}).</p>
                        @endif
                    </div>
                </div>

                @error('profile')
                    <p class="form-note">
                        The report needs a complete profile.
                        <a class="text-action" href="{{ route('account') }}">Update account</a>
                    </p>
                @enderror

                <div class="report-quick-actions">
                    <button type="button" data-report-period="previous-month">Previous month</button>
                    <button type="button" data-report-period="current-month">Current month</button>
                </div>
                <form method="POST" action="{{ route('web.reports.routes.pdf') }}" class="compact-form" data-report-form>
                    @csrf
                    <label>
                        <span>From</span>
                        <input name="from" type="date" value="{{ $reportFrom }}" required>
                    </label>
                    <label>
                        <span>To</span>
                        <input name="to" type="date" value="{{ $reportTo }}" required>
                    </label>
                    <button type="submit" class="secondary-action">Download PDF</button>
                </form>
            </section>
        </aside>

        <section class="routes-panel">
            <div class="section-title list-title">
                <div>
                    <h2>Report history</h2>
                    <p>{{ $reports->total() }} {{ \Illuminate\Support\Str::plural('report', $reports->total()) }}</p>
                </div>
            </div>

            @if ($reports->isEmpty())
                <div class="empty-state">
                    <h3>No reports yet</h3>
                    <p>Generated reports appear here and can be downloaded again at any time.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th scope="col">Period</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col" class="numeric">Distance</th>
                                <th scope="col" class="numeric">Odometer</th>
                                <th scope="col">Generated</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $report)
                                <tr>
                                    <td data-label="Period">{{ $report->period_from->format('d.m.Y') }} – {{ $report->period_to->format('d.m.Y') }}</td>
                                    <td data-label="Vehicle">{{ $report->vehicle_snapshot['registration_number'] ?? '—' }}</td>
                                    <td data-label="Distance" class="numeric">{{ $kmLabel($report->total_distance_km) }}</td>
                                    <td data-label="Odometer" class="numeric">{{ number_format($report->odometer_start_km, 1, '.', ' ') }} → {{ number_format($report->odometer_end_km, 1, '.', ' ') }}</td>
                                    <td data-label="Generated">{{ $report->generated_at->format('d.m.Y H:i') }}</td>
                                    <td class="actions">
                                        <a class="ghost-action" href="{{ route('web.reports.pdf', $report) }}">Download</a>
                                        @if (in_array($report->id, $deletableReportIds, true))
                                            <form method="POST" action="{{ route('web.reports.destroy', $report) }}" data-confirm="Delete this report? Its start reading goes back to the profile mileage.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger-action">Delete</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($reports->hasPages())
                    <nav class="pagination" aria-label="Pagination">
                        @if ($reports->onFirstPage())
                            <button type="button" class="ghost-action" disabled>Previous</button>
                        @else
                            <a class="ghost-action" href="{{ $reports->previousPageUrl() }}">Previous</a>
                        @endif

                        <span>{{ $reports->currentPage() }} / {{ $reports->lastPage() }}</span>

                        @if ($reports->hasMorePages())
                            <a class="ghost-action" href="{{ $reports->nextPageUrl() }}">Next</a>
                        @else
                            <button type="button" class="ghost-action" disabled>Next</button>
                        @endif
                    </nav>
                @endif
            @endif
        </section>
    </section>
@endsection
