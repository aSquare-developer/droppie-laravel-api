const root = document.getElementById('app');
const apiBase = root?.dataset.apiBase || '/api';

const storageKeys = {
    token: 'droppie_track_token',
    user: 'droppie_track_user',
};

const state = {
    authMode: 'login',
    token: localStorage.getItem(storageKeys.token),
    user: readStoredUser(),
    routes: [],
    meta: null,
    summary: null,
    filters: {
        search: '',
        sort: '-created_at',
        page: 1,
    },
    report: {
        from: dateInput(daysAgo(30)),
        to: dateInput(new Date()),
    },
    editingRoute: null,
    loading: false,
    routeLoading: false,
    profileLoading: false,
    flash: null,
};

let pollTimer = null;

const addressAutocomplete = {
    start: { timer: null, requestId: 0, sessionToken: null },
    end: { timer: null, requestId: 0, sessionToken: null },
};

const statusLabels = {
    pending: 'Queued',
    processing: 'Calculating',
    completed: 'Completed',
    failed: 'Failed',
};

function readStoredUser() {
    try {
        return JSON.parse(localStorage.getItem(storageKeys.user) || 'null');
    } catch {
        return null;
    }
}

function daysAgo(amount) {
    const date = new Date();
    date.setDate(date.getDate() - amount);
    return date;
}

function dateInput(date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatDistance(value) {
    if (value === null || value === undefined || value === '') {
        return 'Not available';
    }

    return `${Number(value).toLocaleString('en-US', {
        maximumFractionDigits: 1,
    })} km`;
}

function formatDateOnly(value) {
    if (!value) {
        return 'No date';
    }

    const parts = String(value).split('-').map(Number);
    const date = parts.length === 3
        ? new Date(parts[0], parts[1] - 1, parts[2])
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'No date';
    }

    return new Intl.DateTimeFormat('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function routeStatus(route) {
    return route.distance_status || 'pending';
}

function routeStatusLabel(route) {
    return statusLabels[routeStatus(route)] || routeStatus(route);
}

function setFlash(type, text) {
    state.flash = { type, text };
}

function clearFlash() {
    state.flash = null;
}

async function requestJson(path, options = {}, withAuth = true) {
    const headers = {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
    };

    if (withAuth && state.token) {
        headers.Authorization = `Bearer ${state.token}`;
    }

    const response = await fetch(`${apiBase}${path}`, {
        ...options,
        headers,
    });

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : null;

    if (!response.ok) {
        if (response.status === 401 && withAuth) {
            clearSession();
        }

        throw new Error(readError(payload, response.statusText));
    }

    return payload;
}

function readError(payload, fallback = 'Unable to complete the request') {
    if (payload?.errors) {
        return Object.values(payload.errors).flat().join(' ');
    }

    return payload?.message || fallback;
}

function persistSession(payload) {
    state.token = payload.token;
    state.user = payload.user;
    localStorage.setItem(storageKeys.token, payload.token);
    localStorage.setItem(storageKeys.user, JSON.stringify(payload.user));
}

function clearSession() {
    state.token = null;
    state.user = null;
    state.routes = [];
    state.meta = null;
    state.summary = null;
    state.editingRoute = null;
    localStorage.removeItem(storageKeys.token);
    localStorage.removeItem(storageKeys.user);
    clearTimeout(pollTimer);
}

async function fetchMe() {
    const payload = await requestJson('/me');
    state.user = payload.user;
    localStorage.setItem(storageKeys.user, JSON.stringify(payload.user));
}

async function fetchRoutes() {
    const params = new URLSearchParams();

    Object.entries(state.filters).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params.set(key, value);
        }
    });

    const payload = await requestJson(`/routes?${params.toString()}`);
    state.routes = payload.data || [];
    state.meta = payload.meta || null;
    state.summary = payload.summary || null;
}

async function boot() {
    if (!state.token) {
        renderAuth();
        return;
    }

    state.loading = true;
    renderDashboard();

    try {
        await Promise.all([fetchMe(), fetchRoutes()]);
    } catch (error) {
        setFlash('error', error.message);
    } finally {
        state.loading = false;
        render();
    }
}

function render() {
    if (!state.token) {
        renderAuth();
        return;
    }

    renderDashboard();
}

function renderAuth() {
    clearTimeout(pollTimer);

    const isRegister = state.authMode === 'register';

    root.innerHTML = `
        <main class="auth-shell">
            <section class="auth-visual-panel" aria-label="Droppie">
                <div class="brand-row">
                    <span class="brand-mark">D</span>
                    <span class="brand-name">Droppie</span>
                </div>
                <div class="route-visual" aria-hidden="true">
                    <span class="route-stop route-stop-start"></span>
                    <span class="route-line"></span>
                    <span class="route-pin">24.8</span>
                    <span class="route-stop route-stop-end"></span>
                </div>
                <div class="auth-copy">
                    <h1>Route management dashboard</h1>
                    <p>Manage addresses, distances, and reports in one web interface.</p>
                </div>
            </section>

            <section class="auth-card" aria-label="Authentication">
                <div class="mobile-brand">
                    <span class="brand-mark">DT</span>
                    <span class="brand-name">Droppie</span>
                </div>

                ${renderFlash()}

                <div class="segmented" role="tablist" aria-label="Authentication mode">
                    <button type="button" class="${state.authMode === 'login' ? 'active' : ''}" data-auth-mode="login">Sign in</button>
                    <button type="button" class="${isRegister ? 'active' : ''}" data-auth-mode="register">Register</button>
                </div>

                <form id="auth-form" class="stacked-form">
                    ${isRegister ? `
                        <label>
                            <span>Name</span>
                            <input name="name" type="text" autocomplete="name" required>
                        </label>
                    ` : ''}
                    <label>
                        <span>Email</span>
                        <input name="email" type="email" autocomplete="email" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="${isRegister ? 'new-password' : 'current-password'}" required minlength="6">
                    </label>
                    <button class="primary-action" type="submit" ${state.loading ? 'disabled' : ''}>
                        ${state.loading ? 'Please wait...' : isRegister ? 'Create account' : 'Sign in'}
                    </button>
                </form>
            </section>
        </main>
    `;

    root.querySelectorAll('[data-auth-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            state.authMode = button.dataset.authMode;
            clearFlash();
            renderAuth();
        });
    });

    root.querySelector('[data-action="clear-flash"]')?.addEventListener('click', () => {
        clearFlash();
        renderAuth();
    });

    root.querySelector('#auth-form')?.addEventListener('submit', handleAuthSubmit);
}

function renderDashboard() {
    const metrics = getMetrics();
    const editing = state.editingRoute;

    root.innerHTML = `
        <main class="app-shell">
            <header class="topbar">
                <div class="brand-row compact">
                    <span class="brand-mark">DT</span>
                    <div>
                        <span class="brand-name">Droppie</span>
                        <span class="brand-subtitle">Web dashboard</span>
                    </div>
                </div>
                <div class="user-block">
                    <span>${escapeHtml(state.user?.name || 'User')}</span>
                    <button type="button" class="ghost-action" data-action="logout">Log out</button>
                </div>
            </header>

            ${renderFlash()}

            <section class="overview-band" aria-label="Route overview">
                <div class="route-ribbon" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </div>
                <div class="metric-strip">
                    ${renderMetric('Routes', metrics.totalRoutes, 'Total matching routes')}
                    ${renderMetric('Kilometers', metrics.totalDistance, 'Total route distance')}
                    ${renderMetric('In progress', metrics.activeRoutes, 'Queued and calculating')}
                </div>
            </section>

            <section class="workspace">
                <aside class="control-panel">
                    <section class="panel-section">
                        <div class="section-title">
                            <h2>${editing ? 'Edit route' : 'New route'}</h2>
                            ${editing ? '<button type="button" class="text-action" data-action="cancel-edit">Cancel</button>' : ''}
                        </div>
                        <form id="route-form" class="stacked-form">
                            <label class="address-field">
                                <span>From</span>
                                <div class="address-input-wrap">
                                    <input name="start_address_display" data-address-input="start" type="text" required maxlength="255" autocomplete="off" placeholder="Street, building number, city" value="${escapeHtml(editing?.start_address || '')}">
                                    <input name="start_place_id" data-address-place-id="start" type="hidden" value="${escapeHtml(editing?.start_place_id || '')}">
                                    <input name="start_address_session_token" data-address-session-token="start" type="hidden" value="">
                                    <div class="address-suggestions" data-address-suggestions="start" hidden></div>
                                </div>
                                <small class="address-hint" data-address-hint="start">${escapeHtml(addressHint(editing, 'start'))}</small>
                            </label>
                            <label class="address-field">
                                <span>To</span>
                                <div class="address-input-wrap">
                                    <input name="end_address_display" data-address-input="end" type="text" required maxlength="255" autocomplete="off" placeholder="Street, building number, city" value="${escapeHtml(editing?.end_address || '')}">
                                    <input name="end_place_id" data-address-place-id="end" type="hidden" value="${escapeHtml(editing?.end_place_id || '')}">
                                    <input name="end_address_session_token" data-address-session-token="end" type="hidden" value="">
                                    <div class="address-suggestions" data-address-suggestions="end" hidden></div>
                                </div>
                                <small class="address-hint" data-address-hint="end">${escapeHtml(addressHint(editing, 'end'))}</small>
                            </label>
                            <label>
                                <span>Trip date</span>
                                <input name="started_at" type="date" required value="${escapeHtml(editing?.started_at || dateInput(new Date()))}">
                            </label>
                            <button class="primary-action" type="submit" ${state.routeLoading ? 'disabled' : ''}>
                                ${state.routeLoading ? 'Saving...' : editing ? 'Save' : 'Add route'}
                            </button>
                        </form>
                    </section>

                    <section class="panel-section">
                        <div class="section-title">
                            <h2>Profile</h2>
                        </div>
                        <form id="profile-form" class="stacked-form profile-form">
                            <div class="profile-form-grid">
                                <label>
                                    <span>First name</span>
                                    <input name="name" type="text" autocomplete="given-name" required maxlength="255" value="${escapeHtml(state.user?.name || '')}">
                                </label>
                                <label>
                                    <span>Last name</span>
                                    <input name="last_name" type="text" autocomplete="family-name" maxlength="255" value="${escapeHtml(state.user?.last_name || '')}">
                                </label>
                                <label>
                                    <span>Email</span>
                                    <input type="email" autocomplete="email" readonly aria-readonly="true" class="readonly-field" value="${escapeHtml(state.user?.email || '')}">
                                </label>
                                <label>
                                    <span>Company name</span>
                                    <input name="company_name" type="text" autocomplete="organization" maxlength="255" value="${escapeHtml(state.user?.company_name || '')}">
                                </label>
                                <label>
                                    <span>Vehicle registration number</span>
                                    <input name="car_registration_number" type="text" maxlength="50" value="${escapeHtml(state.user?.car_registration_number || '')}">
                                </label>
                                <label>
                                    <span>Vehicle make and model</span>
                                    <input name="car_make_model" type="text" maxlength="255" value="${escapeHtml(state.user?.car_make_model || '')}">
                                </label>
                                <label>
                                    <span>Vehicle mileage</span>
                                    <input name="car_mileage" type="number" min="0" step="1" inputmode="numeric" value="${escapeHtml(state.user?.car_mileage ?? '')}">
                                </label>
                                <label>
                                    <span>Country of residence</span>
                                    <input name="country" type="text" autocomplete="country-name" maxlength="100" value="${escapeHtml(state.user?.country || '')}">
                                </label>
                            </div>
                            <button class="secondary-action" type="submit" ${state.profileLoading ? 'disabled' : ''}>
                                ${state.profileLoading ? 'Saving...' : 'Save profile'}
                            </button>
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
                        <form id="report-form" class="compact-form">
                            <label>
                                <span>From</span>
                                <input name="from" type="date" value="${escapeHtml(state.report.from)}" required>
                            </label>
                            <label>
                                <span>To</span>
                                <input name="to" type="date" value="${escapeHtml(state.report.to)}" required>
                            </label>
                            <button type="submit" class="secondary-action">Download PDF</button>
                        </form>
                    </section>
                </aside>

                <section class="routes-panel">
                    <div class="section-title list-title">
                        <div>
                            <h2>Routes</h2>
                            <p>${state.meta?.total ?? state.routes.length} records</p>
                        </div>
                    </div>

                    <form id="filter-form" class="filters-bar">
                        <label class="search-field">
                            <span>Search</span>
                            <input name="search" type="search" value="${escapeHtml(state.filters.search)}" placeholder="Address">
                        </label>
                        <label>
                            <span>Sort by</span>
                            <select name="sort">
                                ${sortOption('-started_at', 'Latest trips')}
                                ${sortOption('started_at', 'Earliest trips')}
                                ${sortOption('-distance_km', 'Longest first')}
                                ${sortOption('distance_km', 'Shortest first')}
                                ${sortOption('-created_at', 'Recently created')}
                                ${sortOption('created_at', 'Oldest created')}
                            </select>
                        </label>
                        <div class="filter-actions">
                            <button type="submit" class="secondary-action">Apply</button>
                            <button type="button" class="ghost-action" data-action="reset-filters">Reset</button>
                        </div>
                    </form>

                    <div class="routes-list" aria-live="polite">
                        ${state.loading ? renderLoadingRows() : renderRoutes()}
                    </div>

                    ${renderPagination()}
                </section>
            </section>
        </main>
    `;

    wireDashboard();
    schedulePolling();
}

function renderFlash() {
    if (!state.flash) {
        return '';
    }

    return `
        <div class="flash flash-${escapeHtml(state.flash.type)} mb-4">
            <span>${escapeHtml(state.flash.text)}</span>
            <button type="button" aria-label="Close message" data-action="clear-flash">x</button>
        </div>
    `;
}

function renderMetric(label, value, note) {
    return `
        <article class="metric-tile">
            <span>${escapeHtml(label)}</span>
            <strong>${escapeHtml(value)}</strong>
            <small>${escapeHtml(note)}</small>
        </article>
    `;
}

function getMetrics() {
    const activeRoutes = state.routes.filter((route) => ['pending', 'processing'].includes(routeStatus(route))).length;
    const totalDistance = Number(state.summary?.total_distance_km || 0);

    return {
        totalRoutes: state.meta?.total ?? state.routes.length,
        activeRoutes,
        totalDistance: Number(totalDistance.toFixed(1)).toLocaleString('en-US'),
    };
}

function sortOption(value, label) {
    return `<option value="${value}" ${state.filters.sort === value ? 'selected' : ''}>${label}</option>`;
}

function addressHint(route, prefix) {
    if (!route?.[`${prefix}_place_id`]) {
        return '';
    }

    return [route[`${prefix}_postal_code`], route[`${prefix}_city`]].filter(Boolean).join(', ');
}

function renderRoutes() {
    if (!state.routes.length) {
        return `
            <div class="empty-state">
                <h3>No routes yet</h3>
                <p>Add your first route using the form on the left.</p>
            </div>
        `;
    }

    return state.routes.map((route) => `
        <article class="route-card status-${escapeHtml(routeStatus(route))}">
            <div class="route-card-main">
                <div class="route-path">
                    <span class="path-dot"></span>
                    <div>
                        <strong>${escapeHtml(route.start_address)}</strong>
                        <span>${escapeHtml(route.end_address)}</span>
                    </div>
                </div>
                <div class="route-meta">
                    <span class="status-pill">${escapeHtml(routeStatusLabel(route))}</span>
                    <strong>${escapeHtml(formatDistance(route.distance_km))}</strong>
                    <small>${escapeHtml(formatDateOnly(route.started_at))}</small>
                </div>
            </div>
            ${route.distance_error ? `
                <p class="route-note">${escapeHtml(route.distance_error)}</p>
            ` : ''}
            <div class="route-actions">
                <button type="button" class="text-action" data-action="edit-route" data-route-id="${route.id}">Edit</button>
                <button type="button" class="danger-action" data-action="delete-route" data-route-id="${route.id}">Delete</button>
            </div>
        </article>
    `).join('');
}

function renderLoadingRows() {
    return Array.from({ length: 3 }).map(() => `
        <article class="route-card skeleton">
            <div></div>
            <div></div>
            <div></div>
        </article>
    `).join('');
}

function renderPagination() {
    if (!state.meta || state.meta.last_page <= 1) {
        return '';
    }

    const current = state.meta.current_page;
    const last = state.meta.last_page;

    return `
        <nav class="pagination" aria-label="Pagination">
            <button type="button" class="ghost-action" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>Previous</button>
            <span>${current} / ${last}</span>
            <button type="button" class="ghost-action" data-page="${current + 1}" ${current >= last ? 'disabled' : ''}>Next</button>
        </nav>
    `;
}

function wireDashboard() {
    root.querySelector('#route-form')?.addEventListener('submit', handleRouteSubmit);
    root.querySelector('#profile-form')?.addEventListener('submit', handleProfileSubmit);
    root.querySelector('#filter-form')?.addEventListener('submit', handleFilterSubmit);
    root.querySelector('#report-form')?.addEventListener('submit', handleReportSubmit);
    wireAddressAutocomplete();

    root.querySelectorAll('[data-report-period]').forEach((button) => {
        button.addEventListener('click', handleQuickReportDownload);
    });

    root.querySelectorAll('[data-action]').forEach((element) => {
        element.addEventListener('click', handleActionClick);
    });

    root.querySelectorAll('[data-page]').forEach((button) => {
        button.addEventListener('click', async () => {
            state.filters.page = Number(button.dataset.page);
            await refreshRoutes();
        });
    });
}

function wireAddressAutocomplete() {
    root.querySelectorAll('[data-address-input]').forEach((input) => {
        input.addEventListener('input', () => handleAddressInput(input));
        input.addEventListener('blur', () => {
            window.setTimeout(() => hideAddressSuggestions(input.dataset.addressInput), 160);
        });
    });
}

function handleAddressInput(input) {
    const field = input.dataset.addressInput;
    const lookup = addressAutocomplete[field];
    const query = input.value.trim();

    root.querySelector(`[data-address-place-id="${field}"]`).value = '';
    root.querySelector(`[data-address-session-token="${field}"]`).value = '';
    setAddressHint(field, query ? 'Select an address from the suggestions.' : '');

    clearTimeout(lookup.timer);

    if (query.length < 3) {
        hideAddressSuggestions(field);
        return;
    }

    if (!lookup.sessionToken) {
        lookup.sessionToken = createAddressSessionToken();
    }

    lookup.timer = window.setTimeout(() => {
        fetchAddressSuggestions(field, query);
    }, 260);
}

async function fetchAddressSuggestions(field, query) {
    const lookup = addressAutocomplete[field];
    const requestId = ++lookup.requestId;
    const params = new URLSearchParams({
        input: query,
        session_token: lookup.sessionToken,
    });

    setAddressHint(field, 'Searching for an address...');

    try {
        const payload = await requestJson(`/addresses/autocomplete?${params.toString()}`);

        if (requestId !== lookup.requestId) {
            return;
        }

        renderAddressSuggestions(field, payload.data || []);
        setAddressHint(field, payload.data?.length ? 'Select the exact match.' : 'Address not found.');
    } catch (error) {
        if (requestId !== lookup.requestId) {
            return;
        }

        hideAddressSuggestions(field);
        setAddressHint(field, error.message, 'error');
    }
}

function renderAddressSuggestions(field, suggestions) {
    const container = root.querySelector(`[data-address-suggestions="${field}"]`);

    if (!container) {
        return;
    }

    if (!suggestions.length) {
        container.hidden = true;
        container.innerHTML = '';
        return;
    }

    container.innerHTML = suggestions.map((suggestion, index) => `
        <button type="button" data-address-suggestion="${index}">
            <strong>${escapeHtml(suggestion.main_text || suggestion.description)}</strong>
            ${suggestion.secondary_text ? `<span>${escapeHtml(suggestion.secondary_text)}</span>` : ''}
        </button>
    `).join('');
    container.hidden = false;

    container.querySelectorAll('[data-address-suggestion]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const suggestion = suggestions[Number(button.dataset.addressSuggestion)];
            selectAddressSuggestion(field, suggestion);
        });
    });
}

async function selectAddressSuggestion(field, suggestion) {
    const lookup = addressAutocomplete[field];
    const input = root.querySelector(`[data-address-input="${field}"]`);
    const placeId = root.querySelector(`[data-address-place-id="${field}"]`);
    const sessionToken = root.querySelector(`[data-address-session-token="${field}"]`);

    input.value = suggestion.description;
    placeId.value = '';
    sessionToken.value = lookup.sessionToken || '';
    hideAddressSuggestions(field);
    setAddressHint(field, 'Validating address...');

    try {
        const payload = await requestJson('/addresses/validate', {
            method: 'POST',
            body: JSON.stringify({
                place_id: suggestion.place_id,
                session_token: lookup.sessionToken,
            }),
        });

        input.value = payload.data.formatted_address;
        placeId.value = payload.data.place_id;
        setAddressHint(field, [payload.data.postal_code, payload.data.city].filter(Boolean).join(', '), 'success');
        lookup.sessionToken = null;
    } catch (error) {
        placeId.value = '';
        setAddressHint(field, error.message, 'error');
    }
}

function hideAddressSuggestions(field) {
    const container = root.querySelector(`[data-address-suggestions="${field}"]`);

    if (!container) {
        return;
    }

    container.hidden = true;
    container.innerHTML = '';
}

function setAddressHint(field, text, type = '') {
    const hint = root.querySelector(`[data-address-hint="${field}"]`);

    if (!hint) {
        return;
    }

    hint.textContent = text || '';
    hint.dataset.state = type;
}

function createAddressSessionToken() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

async function handleAuthSubmit(event) {
    event.preventDefault();
    clearFlash();
    const formData = new FormData(event.currentTarget);
    const payload = {
        email: String(formData.get('email') || '').trim(),
        password: String(formData.get('password') || ''),
    };

    if (state.authMode === 'register') {
        payload.name = String(formData.get('name') || '').trim();
    }

    state.loading = true;
    renderAuth();

    try {
        const response = await requestJson(
            state.authMode === 'register' ? '/register' : '/login',
            {
                method: 'POST',
                body: JSON.stringify(payload),
            },
            false,
        );

        persistSession(response);
        setFlash('success', 'You are signed in to Droppie.');
        await fetchRoutes();
    } catch (error) {
        setFlash('error', error.message);
    } finally {
        state.loading = false;
        render();
    }
}

async function handleRouteSubmit(event) {
    event.preventDefault();
    clearFlash();
    const formData = new FormData(event.currentTarget);
    const startPlaceId = String(formData.get('start_place_id') || '').trim();
    const endPlaceId = String(formData.get('end_place_id') || '').trim();

    if (!startPlaceId || !endPlaceId) {
        setFlash('error', 'Select exact departure and destination addresses from the suggestions.');
        renderDashboard();
        return;
    }

    const payload = {
        started_at: String(formData.get('started_at') || '').trim(),
    };

    if (!state.editingRoute || startPlaceId !== String(state.editingRoute.start_place_id || '')) {
        payload.start_place_id = startPlaceId;
        payload.start_address_session_token = String(formData.get('start_address_session_token') || '').trim();
    }

    if (!state.editingRoute || endPlaceId !== String(state.editingRoute.end_place_id || '')) {
        payload.end_place_id = endPlaceId;
        payload.end_address_session_token = String(formData.get('end_address_session_token') || '').trim();
    }

    state.routeLoading = true;
    renderDashboard();

    try {
        if (state.editingRoute) {
            await requestJson(`/routes/${state.editingRoute.id}`, {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
            setFlash('success', 'Route updated.');
        } else {
            await requestJson('/routes', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            setFlash('success', 'Route added and queued for distance calculation.');
        }

        state.editingRoute = null;
        state.filters.page = 1;
        await fetchRoutes();
    } catch (error) {
        setFlash('error', error.message);
    } finally {
        state.routeLoading = false;
        renderDashboard();
    }
}

async function handleProfileSubmit(event) {
    event.preventDefault();
    clearFlash();

    const formData = new FormData(event.currentTarget);
    const mileage = String(formData.get('car_mileage') || '').trim();
    const payload = {
        name: String(formData.get('name') || '').trim(),
        last_name: String(formData.get('last_name') || '').trim(),
        company_name: String(formData.get('company_name') || '').trim(),
        car_registration_number: String(formData.get('car_registration_number') || '').trim(),
        car_make_model: String(formData.get('car_make_model') || '').trim(),
        car_mileage: mileage === '' ? null : Number(mileage),
        country: String(formData.get('country') || '').trim(),
    };

    state.profileLoading = true;
    renderDashboard();

    try {
        const response = await requestJson('/profile', {
            method: 'PATCH',
            body: JSON.stringify(payload),
        });

        state.user = response.user;
        localStorage.setItem(storageKeys.user, JSON.stringify(response.user));
        setFlash('success', 'Profile saved.');
    } catch (error) {
        setFlash('error', error.message);
    } finally {
        state.profileLoading = false;
        renderDashboard();
    }
}

async function handleFilterSubmit(event) {
    event.preventDefault();
    clearFlash();

    const formData = new FormData(event.currentTarget);
    state.filters = {
        search: String(formData.get('search') || '').trim(),
        sort: String(formData.get('sort') || '-created_at'),
        page: 1,
    };

    await refreshRoutes();
}

async function handleReportSubmit(event) {
    event.preventDefault();
    clearFlash();

    const formData = new FormData(event.currentTarget);
    await downloadReport({
        from: String(formData.get('from') || ''),
        to: String(formData.get('to') || ''),
    });
}

async function handleQuickReportDownload(event) {
    clearFlash();

    const period = event.currentTarget.dataset.reportPeriod;
    await downloadReport(period === 'previous-month' ? monthReportRange(-1) : monthReportRange(0));
}

async function downloadReport(report) {
    state.report = report;

    try {
        const params = new URLSearchParams(state.report);
        const response = await fetch(`${apiBase}/reports/routes/pdf?${params.toString()}`, {
            headers: {
                Accept: 'application/pdf',
                Authorization: `Bearer ${state.token}`,
            },
        });

        if (!response.ok) {
            const contentType = response.headers.get('content-type') || '';
            const payload = contentType.includes('application/json') ? await response.json() : null;
            throw new Error(readError(payload, 'Unable to download the report'));
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `routes-report-${state.report.from}-${state.report.to}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        setFlash('success', 'PDF report downloaded.');
    } catch (error) {
        setFlash('error', error.message);
    } finally {
        renderDashboard();
    }
}

function monthReportRange(offset) {
    const today = new Date();
    const from = new Date(today.getFullYear(), today.getMonth() + offset, 1);
    const to = offset === 0
        ? today
        : new Date(today.getFullYear(), today.getMonth() + offset + 1, 0);

    return {
        from: dateInput(from),
        to: dateInput(to),
    };
}

async function handleActionClick(event) {
    const action = event.currentTarget.dataset.action;

    if (action === 'clear-flash') {
        clearFlash();
        render();
        return;
    }

    if (action === 'logout') {
        await logout();
        return;
    }

    if (action === 'cancel-edit') {
        state.editingRoute = null;
        clearFlash();
        renderDashboard();
        return;
    }

    if (action === 'reset-filters') {
        state.filters = {
            search: '',
            sort: '-created_at',
            page: 1,
        };
        await refreshRoutes();
        return;
    }

    if (action === 'edit-route') {
        const route = state.routes.find((item) => String(item.id) === event.currentTarget.dataset.routeId);
        state.editingRoute = route ? { ...route } : null;
        clearFlash();
        renderDashboard();
        return;
    }

    if (action === 'delete-route') {
        const routeId = event.currentTarget.dataset.routeId;
        const confirmed = window.confirm('Delete this route?');

        if (!confirmed) {
            return;
        }

        try {
            await requestJson(`/routes/${routeId}`, { method: 'DELETE' });
            setFlash('success', 'Route deleted.');
            await fetchRoutes();
        } catch (error) {
            setFlash('error', error.message);
        } finally {
            renderDashboard();
        }
    }
}

async function refreshRoutes() {
    state.loading = true;
    renderDashboard();

    try {
        await fetchRoutes();
    } catch (error) {
        setFlash('error', error.message);
    } finally {
        state.loading = false;
        renderDashboard();
    }
}

async function logout() {
    try {
        await requestJson('/logout', { method: 'POST' });
    } catch {
        // Local session cleanup is enough if the token has already expired.
    } finally {
        clearSession();
        setFlash('success', 'You have been signed out.');
        renderAuth();
    }
}

function schedulePolling() {
    clearTimeout(pollTimer);

    const hasPendingRoutes = state.routes.some((route) => ['pending', 'processing'].includes(routeStatus(route)));

    if (!state.token || !hasPendingRoutes) {
        return;
    }

    pollTimer = setTimeout(async () => {
        try {
            await fetchRoutes();
        } catch {
            return;
        }

        renderDashboard();
    }, 8000);
}

boot();
