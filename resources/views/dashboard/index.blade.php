@extends('layouts.app')

@section('title', 'Dashboard - Droppie')

@section('body')
    @php
        $profile = $user->profile;
        $vehicle = $user->activeVehicle;
        $activeRoutes = $routes->getCollection()->filter->isDistanceCalculationInProgress()->count();
        $distanceLabel = fn ($value) => blank($value) ? 'Not available' : number_format((float) $value, 1, '.', ',').' km';
        $dateLabel = fn ($value) => $value ? $value->format('d M Y') : 'No date';
        $reportFrom = old('from', now()->subDays(30)->toDateString());
        $reportTo = old('to', now()->toDateString());
        $editingStart = $editingRoute?->startAddress;
        $editingEnd = $editingRoute?->endAddress;
    @endphp

    <main
        class="app-shell"
        data-route-status-url="{{ route('web.routes.status') }}"
        data-address-autocomplete-url="{{ route('web.addresses.autocomplete') }}"
        data-address-validate-url="{{ route('web.addresses.validate') }}"
    >
        <header class="topbar">
            <div class="brand-row compact">
                <span class="brand-mark">D</span>
                <div>
                    <span class="brand-name">Droppie</span>
                    <span class="brand-subtitle">Web dashboard</span>
                </div>
            </div>
            <div class="user-block">
                <span>{{ $profile?->first_name ?? 'User' }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="ghost-action">Log out</button>
                </form>
            </div>
        </header>

        @include('partials.flash')

        <section class="overview-band" aria-label="Route overview">
            <div class="route-ribbon" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="metric-strip">
                <article class="metric-tile">
                    <span>Routes</span>
                    <strong>{{ $routes->total() }}</strong>
                    <small>Total matching routes</small>
                </article>
                <article class="metric-tile">
                    <span>Kilometers</span>
                    <strong>{{ number_format((float) $summary['total_distance_km'], 1, '.', ',') }}</strong>
                    <small>Total route distance</small>
                </article>
                <article class="metric-tile">
                    <span>In progress</span>
                    <strong>{{ $activeRoutes }}</strong>
                    <small>Queued and calculating</small>
                </article>
            </div>
        </section>

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

                <section class="panel-section">
                    <div class="section-title">
                        <h2>Profile</h2>
                    </div>
                    <form method="POST" action="{{ route('profile.update') }}" class="stacked-form profile-form">
                        @csrf
                        @method('PATCH')
                        <div class="profile-form-grid">
                            <label>
                                <span>First name</span>
                                <input name="name" type="text" autocomplete="given-name" required maxlength="255" value="{{ old('name', $profile?->first_name) }}">
                            </label>
                            <label>
                                <span>Last name</span>
                                <input name="last_name" type="text" autocomplete="family-name" maxlength="255" value="{{ old('last_name', $profile?->last_name) }}">
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" autocomplete="email" readonly aria-readonly="true" class="readonly-field" value="{{ $user->email }}">
                            </label>
                            <label>
                                <span>Company name</span>
                                <input name="company_name" type="text" autocomplete="organization" maxlength="255" value="{{ old('company_name', $profile?->company_name) }}">
                            </label>
                            <label>
                                <span>Vehicle registration number</span>
                                <input name="car_registration_number" type="text" maxlength="50" value="{{ old('car_registration_number', $vehicle?->registration_number) }}">
                            </label>
                            <label>
                                <span>Vehicle make and model</span>
                                <input name="car_make_model" type="text" maxlength="255" value="{{ old('car_make_model', $vehicle?->make_model) }}">
                            </label>
                            <label>
                                <span>Vehicle mileage</span>
                                <input name="car_mileage" type="number" min="0" step="0.1" inputmode="decimal" value="{{ old('car_mileage', $vehicle?->odometer_km) }}">
                            </label>
                            <label>
                                <span>Country of residence</span>
                                <input name="country" type="text" autocomplete="country-name" maxlength="100" value="{{ old('country', $profile?->country) }}">
                            </label>
                        </div>
                        <button class="secondary-action" type="submit">Save profile</button>
                    </form>
                </section>

                <section class="panel-section report-section">
                    <div class="section-title">
                        <h2>PDF report</h2>
                    </div>
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
    </main>
@endsection
