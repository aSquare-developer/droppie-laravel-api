@extends('layouts.workspace')

@section('title', 'Admin - Droppie')

@section('content')
    @php
        $n = fn ($value) => number_format((int) $value, 0, '.', ' ');
        $users = $metrics['users'];
        $routes = $metrics['routes'];
        $addresses = $metrics['addresses'];
        $google = $metrics['google'];
        $queue = $metrics['queue'];
        $cache = $metrics['cache'];
        $age = function (?int $seconds): string {
            if ($seconds === null) {
                return '—';
            }

            return \Carbon\CarbonInterval::seconds($seconds)->cascade()->forHumans(['short' => true, 'parts' => 2]);
        };
        $serviceLabels = [
            'routes' => 'Routes (Compute Routes)',
            'routes_cache_hit' => 'Routes cache hits (no request)',
            'places_autocomplete' => 'Places Autocomplete',
            'places_details' => 'Place Details',
            'places_find_place' => 'Find Place',
            'address_validation' => 'Address Validation',
        ];
        $services = array_unique([...array_keys($serviceLabels), ...array_keys($google['month']), ...array_keys($google['today'])]);
    @endphp

    @if ($google['routes_warning'])
        <div class="notice-bar" role="alert">
            <span>Google Routes: {{ $n($google['routes_month']) }} requests this month. The free Compute Routes Essentials limit is 10 000 per month.</span>
        </div>
    @endif

    <dl class="metric-bar" aria-label="Overview">
        <div class="metric-item"><dt>Users</dt><dd>{{ $n($users['total']) }}</dd></div>
        <div class="metric-item"><dt>Active today</dt><dd>{{ $n($users['active_today']) }}</dd></div>
        <div class="metric-item"><dt>Routes today</dt><dd>{{ $n($routes['today']) }}</dd></div>
        <div class="metric-item"><dt>Queued jobs</dt><dd>{{ $n($queue['pending']) }}</dd></div>
        <div class="metric-item"><dt>Failed jobs</dt><dd>{{ $n($queue['failed']) }}</dd></div>
    </dl>

    <div class="admin-grid">
        <section class="panel-section">
            <div class="section-title"><h2>Users</h2></div>
            <table class="admin-table">
                <tbody>
                    <tr><th scope="row">Total</th><td class="numeric">{{ $n($users['total']) }}</td></tr>
                    <tr><th scope="row">New in 7 days</th><td class="numeric">{{ $n($users['new_7_days']) }}</td></tr>
                    <tr><th scope="row">New in 30 days</th><td class="numeric">{{ $n($users['new_30_days']) }}</td></tr>
                    <tr><th scope="row">Active today</th><td class="numeric">{{ $n($users['active_today']) }}</td></tr>
                    <tr><th scope="row">Active in 7 days</th><td class="numeric">{{ $n($users['active_7_days']) }}</td></tr>
                    <tr><th scope="row">Active in 30 days</th><td class="numeric">{{ $n($users['active_30_days']) }}</td></tr>
                    <tr><th scope="row">Signed in with Google</th><td class="numeric">{{ $n($users['google']) }}</td></tr>
                    @foreach ($users['by_role'] as $role => $count)
                        <tr><th scope="row">Role: {{ $role }}</th><td class="numeric">{{ $n($count) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="panel-section">
            <div class="section-title"><h2>Admins and moderators</h2></div>
            @if ($users['staff'] === [])
                <p class="admin-note">No admins or moderators.</p>
            @else
                <table class="admin-table">
                    <tbody>
                        @foreach ($users['staff'] as $member)
                            <tr><th scope="row">{{ $member['email'] }}</th><td>{{ $member['role'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <p class="admin-note">Roles change only with <code>php artisan users:set-role</code>.</p>
        </section>

        <section class="panel-section">
            <div class="section-title"><h2>Routes</h2></div>
            <table class="admin-table">
                <tbody>
                    <tr><th scope="row">Total</th><td class="numeric">{{ $n($routes['total']) }}</td></tr>
                    <tr><th scope="row">Added today</th><td class="numeric">{{ $n($routes['today']) }}</td></tr>
                    <tr><th scope="row">Added in 7 days</th><td class="numeric">{{ $n($routes['last_7_days']) }}</td></tr>
                    <tr><th scope="row">Added in 30 days</th><td class="numeric">{{ $n($routes['last_30_days']) }}</td></tr>
                    @foreach ($routes['by_status'] as $status => $count)
                        <tr><th scope="row">Distance {{ $status }}</th><td class="numeric">{{ $n($count) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="panel-section">
            <div class="section-title"><h2>Google API</h2></div>
            <table class="admin-table">
                <thead>
                    <tr><th scope="col">Service</th><th scope="col" class="numeric">Today</th><th scope="col" class="numeric">This month</th></tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr>
                            <th scope="row">{{ $serviceLabels[$service] ?? $service }}</th>
                            <td class="numeric">{{ $n($google['today'][$service] ?? 0) }}</td>
                            <td class="numeric">{{ $n($google['month'][$service] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="admin-note">
                Distance cache hit rate this month:
                {{ $google['month_cache_hit_rate'] === null ? '—' : $google['month_cache_hit_rate'].' %' }}.
                A warning appears above {{ $n($routesWarning) }} Routes requests a month.
            </p>
        </section>

        <section class="panel-section">
            <div class="section-title"><h2>Most used addresses</h2></div>
            @if ($addresses['top'] === [])
                <p class="admin-note">No addresses used yet.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr><th scope="col">Address</th><th scope="col" class="numeric">Uses</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($addresses['top'] as $address)
                            <tr><th scope="row">{{ $address['address'] }}</th><td class="numeric">{{ $n($address['uses']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <p class="admin-note">{{ $n($addresses['total']) }} addresses stored, {{ $n($addresses['new_30_days']) }} added in 30 days.</p>
        </section>

        <section class="panel-section">
            <div class="section-title"><h2>Reports, cache and queue</h2></div>
            <table class="admin-table">
                <tbody>
                    <tr><th scope="row">PDF reports</th><td class="numeric">{{ $n($metrics['reports']['total']) }}</td></tr>
                    <tr><th scope="row">PDF reports in 30 days</th><td class="numeric">{{ $n($metrics['reports']['last_30_days']) }}</td></tr>
                    @if ($cache['available'])
                        <tr><th scope="row">Cached route distances</th><td class="numeric">{{ $n($cache['distance_entries']) }}</td></tr>
                        <tr><th scope="row">Cache table rows</th><td class="numeric">{{ $n($cache['total_rows']) }}</td></tr>
                    @else
                        <tr><th scope="row">Cache</th><td>not the database store</td></tr>
                    @endif
                    <tr><th scope="row">Queued jobs</th><td class="numeric">{{ $n($queue['pending']) }}</td></tr>
                    <tr><th scope="row">Oldest queued job</th><td class="numeric">{{ $age($queue['oldest_age_seconds']) }}</td></tr>
                    <tr><th scope="row">Failed jobs</th><td class="numeric">{{ $n($queue['failed']) }}</td></tr>
                </tbody>
            </table>
        </section>
    </div>

    <p class="admin-note">Figures from {{ $metrics['generated_at'] }} UTC, refreshed at most every {{ $refreshMinutes }} minutes.</p>
@endsection
