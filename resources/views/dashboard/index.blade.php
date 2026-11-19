@extends('layouts.workspace')

@section('title', 'Trips - Droppie')

@section('shell_attributes')
    data-route-status-url="{{ route('web.routes.status') }}"
    data-address-autocomplete-url="{{ route('web.addresses.autocomplete') }}"
    data-address-validate-url="{{ route('web.addresses.validate') }}"
@endsection

@section('content')
    @php
        $distanceLabel = fn ($value) => blank($value) ? 'Not available' : number_format((float) $value, 1, '.', ',').' km';
        $editingStart = $editingRoute?->startAddress;
        $editingEnd = $editingRoute?->endAddress;
    @endphp

    @unless ($user->activeVehicle)
        <div class="notice-bar" role="status">
            <span>Add an active vehicle to your profile before adding trips.</span>
            <a class="text-action" href="{{ route('account') }}">Open account</a>
        </div>
    @endunless

    <dl class="metric-bar" aria-label="Route overview" data-live-region="metrics">
        <div class="metric-item">
            <dt>Routes</dt>
            <dd>{{ $summary['routes'] }}</dd>
        </div>
        <div class="metric-item">
            <dt>Kilometers</dt>
            <dd>{{ number_format((float) $summary['total_distance_km'], 1, '.', ',') }}</dd>
        </div>
        <div class="metric-item">
            <dt>In progress</dt>
            <dd>{{ $summary['in_progress'] }}</dd>
        </div>
    </dl>

    <section class="workspace">
        <aside class="control-panel">
            <section class="panel-section">
                <div class="section-title">
                    <h2>{{ $editingRoute ? 'Edit route' : 'New route' }}</h2>
                    @if ($editingRoute)
                        <a class="text-action" href="{{ route('dashboard', request()->except('edit')) }}">Cancel</a>
                    @endif
                </div>
                <form
                    method="POST"
                    action="{{ $editingRoute ? route('web.routes.update', $editingRoute) : route('web.routes.store') }}"
                    class="stacked-form"
                    data-route-form
                >
                    @csrf
                    @if ($editingRoute)
                        @method('PUT')
                    @endif

                    <label class="address-field">
                        <span>From</span>
                        <div class="address-input-wrap">
                            <input
                                name="start_address_display"
                                data-address-input="start"
                                type="text"
                                required
                                maxlength="255"
                                autocomplete="off"
                                placeholder="Street, building number, city"
                                value="{{ old('start_address_display', $editingStart?->formatted_address) }}"
                                @if (old('start_place_id', $editingStart?->place_id)) data-address-state="valid" @endif
                            >
                            <input name="start_place_id" data-address-place-id="start" type="hidden" value="{{ old('start_place_id', $editingStart?->place_id) }}">
                            <input name="start_address_session_token" data-address-session-token="start" type="hidden" value="{{ old('start_address_session_token') }}">
                            <div class="address-suggestions" data-address-suggestions="start" hidden></div>
                        </div>
                        <small class="address-hint" data-address-hint="start"></small>
                    </label>

                    <label class="address-field">
                        <span>To</span>
                        <div class="address-input-wrap">
                            <input
                                name="end_address_display"
                                data-address-input="end"
                                type="text"
                                required
                                maxlength="255"
                                autocomplete="off"
                                placeholder="Street, building number, city"
                                value="{{ old('end_address_display', $editingEnd?->formatted_address) }}"
                                @if (old('end_place_id', $editingEnd?->place_id)) data-address-state="valid" @endif
                            >
                            <input name="end_place_id" data-address-place-id="end" type="hidden" value="{{ old('end_place_id', $editingEnd?->place_id) }}">
                            <input name="end_address_session_token" data-address-session-token="end" type="hidden" value="{{ old('end_address_session_token') }}">
                            <div class="address-suggestions" data-address-suggestions="end" hidden></div>
                        </div>
                        <small class="address-hint" data-address-hint="end"></small>
                    </label>

                    <label>
                        <span>Trip date</span>
                        <input name="started_at" type="date" required value="{{ old('started_at', $editingRoute?->started_at?->toDateString() ?? $defaultTripDate) }}">
                    </label>

                    <button class="primary-action" type="submit">{{ $editingRoute ? 'Save' : 'Add route' }}</button>
                </form>
            </section>
        </aside>

        <section class="routes-panel">
            <div class="section-title list-title">
                <div data-live-region="day-summary">
                    @if ($day['date'])
                        <h2>{{ $day['date']->format('l, d M Y') }}</h2>
                        <p>
                            {{ $dayRoutes->count() }} {{ \Illuminate\Support\Str::plural('route', $dayRoutes->count()) }}
                            &middot; {{ number_format($day['distance_km'], 1, '.', ',') }} km
                            @if ($day['position'])
                                &middot; day {{ $day['position'] }} of {{ $day['count'] }}
                            @endif
                        </p>
                    @else
                        <h2>Routes</h2>
                        <p>{{ $summary['routes'] }} records</p>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="filters-bar" role="search">
                <label class="search-field">
                    <span class="sr-only">Search by address</span>
                    <input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search by address">
                </label>
                <div class="filter-actions">
                    <button type="submit" class="secondary-action">Search</button>
                    @if ($filters['search'] !== '')
                        <a class="ghost-action" href="{{ route('dashboard') }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($day['count'] > 0)
                @include('dashboard.day-nav', ['withPicker' => true])
            @endif

            <div class="routes-list" aria-live="polite" data-live-region="routes">
                @forelse ($dayRoutes as $route)
                    @include('dashboard.route-card', [
                        'route' => $route,
                        'statusLabels' => $statusLabels,
                        'distanceLabel' => $distanceLabel,
                    ])
                @empty
                    <div class="empty-state">
                        @if ($day['count'] === 0 && $filters['search'] !== '')
                            <h3>No routes found</h3>
                            <p>No route matches “{{ $filters['search'] }}”.</p>
                        @elseif ($day['count'] === 0)
                            <h3>No routes yet</h3>
                            <p>Add your first route using the form.</p>
                        @else
                            <h3>No trips on this date</h3>
                            <p>Use Newer / Older to go to a date with trips.</p>
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($dayRoutes->count() > 3)
                @include('dashboard.day-nav', ['withPicker' => false])
            @endif
        </section>
    </section>
@endsection
