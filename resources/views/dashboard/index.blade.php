@extends('layouts.workspace')

@section('title', 'Trips - Droppie')

@section('shell_attributes')
    data-route-status-url="{{ route('web.routes.status') }}"
    data-address-autocomplete-url="{{ route('web.addresses.autocomplete') }}"
    data-address-validate-url="{{ route('web.addresses.validate') }}"
@endsection

@section('content')
    @php
        $activeRoutes = $routes->getCollection()->filter->isDistanceCalculationInProgress()->count();
        $distanceLabel = fn ($value) => blank($value) ? 'Not available' : number_format((float) $value, 1, '.', ',').' km';
        $dateLabel = fn ($value) => $value ? $value->format('d M Y') : 'No date';
        $editingStart = $editingRoute?->startAddress;
        $editingEnd = $editingRoute?->endAddress;
    @endphp

    @unless ($user->activeVehicle)
        <div class="notice-bar" role="status">
            <span>Add an active vehicle to your profile before adding trips.</span>
            <a class="text-action" href="{{ route('account') }}">Open account</a>
        </div>
    @endunless

    <dl class="metric-bar" aria-label="Route overview">
        <div class="metric-item">
            <dt>Routes</dt>
            <dd>{{ $routes->total() }}</dd>
        </div>
        <div class="metric-item">
            <dt>Kilometers</dt>
            <dd>{{ number_format((float) $summary['total_distance_km'], 1, '.', ',') }}</dd>
        </div>
        <div class="metric-item">
            <dt>In progress</dt>
            <dd>{{ $activeRoutes }}</dd>
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
                            >
                            <input name="start_place_id" data-address-place-id="start" type="hidden" value="{{ old('start_place_id', $editingStart?->place_id) }}">
                            <input name="start_address_session_token" data-address-session-token="start" type="hidden" value="{{ old('start_address_session_token') }}">
                            <div class="address-suggestions" data-address-suggestions="start" hidden></div>
                        </div>
                        <small class="address-hint" data-address-hint="start">
                            {{ old('start_place_id', $editingStart?->place_id) ? collect([$editingStart?->postal_code, $editingStart?->city])->filter()->implode(', ') : '' }}
                        </small>
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
                            >
                            <input name="end_place_id" data-address-place-id="end" type="hidden" value="{{ old('end_place_id', $editingEnd?->place_id) }}">
                            <input name="end_address_session_token" data-address-session-token="end" type="hidden" value="{{ old('end_address_session_token') }}">
                            <div class="address-suggestions" data-address-suggestions="end" hidden></div>
                        </div>
                        <small class="address-hint" data-address-hint="end">
                            {{ old('end_place_id', $editingEnd?->place_id) ? collect([$editingEnd?->postal_code, $editingEnd?->city])->filter()->implode(', ') : '' }}
                        </small>
                    </label>

                    <label>
                        <span>Trip date</span>
                        <input name="started_at" type="date" required value="{{ old('started_at', $editingRoute?->started_at?->toDateString() ?? now()->toDateString()) }}">
                    </label>

                    <button class="primary-action" type="submit">{{ $editingRoute ? 'Save' : 'Add route' }}</button>
                </form>
            </section>
        </aside>

        <section class="routes-panel">
            <div class="section-title list-title">
                <div>
                    <h2>Routes</h2>
                    <p>{{ $routes->total() }} records</p>
                </div>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="filters-bar">
                <label class="search-field">
                    <span>Search</span>
                    <input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Address">
                </label>
                <label>
                    <span>Sort by</span>
                    <select name="sort">
                        <option value="-started_at" @selected($filters['sort'] === '-started_at')>Latest trips</option>
                        <option value="started_at" @selected($filters['sort'] === 'started_at')>Earliest trips</option>
                        <option value="-distance_km" @selected($filters['sort'] === '-distance_km')>Longest first</option>
                        <option value="distance_km" @selected($filters['sort'] === 'distance_km')>Shortest first</option>
                        <option value="-created_at" @selected($filters['sort'] === '-created_at')>Recently created</option>
                        <option value="created_at" @selected($filters['sort'] === 'created_at')>Oldest created</option>
                    </select>
                </label>
                <div class="filter-actions">
                    <button type="submit" class="secondary-action">Apply</button>
                    <a class="ghost-action" href="{{ route('dashboard') }}">Reset</a>
                </div>
            </form>

            <div class="routes-list" aria-live="polite">
                @forelse ($routes as $route)
                    @include('dashboard.route-card', [
                        'route' => $route,
                        'statusLabels' => $statusLabels,
                        'distanceLabel' => $distanceLabel,
                        'dateLabel' => $dateLabel,
                    ])
                @empty
                    <div class="empty-state">
                        <h3>No routes yet</h3>
                        <p>Add your first route using the form on the left.</p>
                    </div>
                @endforelse
            </div>

            @if ($routes->hasPages())
                <nav class="pagination" aria-label="Pagination">
                    @if ($routes->onFirstPage())
                        <button type="button" class="ghost-action" disabled>Previous</button>
                    @else
                        <a class="ghost-action" href="{{ $routes->previousPageUrl() }}">Previous</a>
                    @endif

                    <span>{{ $routes->currentPage() }} / {{ $routes->lastPage() }}</span>

                    @if ($routes->hasMorePages())
                        <a class="ghost-action" href="{{ $routes->nextPageUrl() }}">Next</a>
                    @else
                        <button type="button" class="ghost-action" disabled>Next</button>
                    @endif
                </nav>
            @endif
        </section>
    </section>
@endsection
