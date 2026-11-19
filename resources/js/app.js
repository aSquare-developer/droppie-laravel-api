const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

const addressAutocomplete = {
    start: { timer: null, requestId: 0, sessionToken: null },
    end: { timer: null, requestId: 0, sessionToken: null },
};

function readError(payload, fallback = 'Unable to complete the request') {
    if (payload?.errors) {
        return Object.values(payload.errors).flat().join(' ');
    }

    return payload?.message || fallback;
}

async function requestJson(url, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.method && options.method !== 'GET' ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        ...(options.headers || {}),
    };

    const response = await fetch(url, {
        ...options,
        headers,
    });

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : null;

    if (!response.ok) {
        throw new Error(readError(payload, response.statusText));
    }

    return payload;
}

function dateInput(date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
}

function createAddressSessionToken() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function appShell() {
    return document.querySelector('.app-shell');
}

function setupFlashDismissal() {
    document.querySelectorAll('[data-action="clear-flash"]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('.flash')?.remove();
        });
    });
}

function setupConfirmForms(root = document) {
    root.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });
}

const THEME_KEY = 'droppie-theme';
const THEMES = ['auto', 'light', 'dark'];
const THEME_LABELS = { auto: 'Auto', light: 'Light', dark: 'Dark' };

function storedTheme() {
    try {
        const theme = localStorage.getItem(THEME_KEY);

        return THEMES.includes(theme) ? theme : 'auto';
    } catch (error) {
        return 'auto';
    }
}

function applyTheme(theme) {
    if (theme === 'auto') {
        delete document.documentElement.dataset.theme;
    } else {
        document.documentElement.dataset.theme = theme;
    }

    try {
        if (theme === 'auto') {
            localStorage.removeItem(THEME_KEY);
        } else {
            localStorage.setItem(THEME_KEY, theme);
        }
    } catch (error) {
        // Private mode or blocked storage: the choice lasts until the page is left.
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const description = theme === 'auto' ? 'follow system' : THEME_LABELS[theme].toLowerCase();

        button.setAttribute('aria-label', `Theme: ${description}`);
        button.title = `Theme: ${description}`;
        button.querySelector('[data-theme-label]').textContent = THEME_LABELS[theme];
        button.querySelectorAll('[data-theme-icon]').forEach((icon) => {
            icon.hidden = icon.dataset.themeIcon !== theme;
        });
    });
}

function setupThemeToggle() {
    let theme = storedTheme();
    applyTheme(theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            theme = THEMES[(THEMES.indexOf(theme) + 1) % THEMES.length];
            applyTheme(theme);
        });
    });
}

function setupAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach((input) => {
        input.addEventListener('change', () => {
            if (input.value) {
                input.form?.requestSubmit();
            }
        });
    });
}

function setupReportQuickActions() {
    const form = document.querySelector('[data-report-form]');

    if (!form) {
        return;
    }

    document.querySelectorAll('[data-report-period]').forEach((button) => {
        button.addEventListener('click', () => {
            const range = monthReportRange(button.dataset.reportPeriod === 'previous-month' ? -1 : 0);

            form.querySelector('[name="from"]').value = range.from;
            form.querySelector('[name="to"]').value = range.to;
            form.requestSubmit();
        });
    });
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

function setupAddressAutocomplete() {
    document.querySelectorAll('[data-address-input]').forEach((input) => {
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

    document.querySelector(`[data-address-place-id="${field}"]`).value = '';
    document.querySelector(`[data-address-session-token="${field}"]`).value = '';
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
    const shell = appShell();
    const lookup = addressAutocomplete[field];
    const requestId = ++lookup.requestId;
    const params = new URLSearchParams({
        input: query,
        session_token: lookup.sessionToken,
    });

    setAddressHint(field, 'Searching for an address...');

    try {
        const payload = await requestJson(`${shell.dataset.addressAutocompleteUrl}?${params.toString()}`);

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
    const container = document.querySelector(`[data-address-suggestions="${field}"]`);

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
            <strong>
                ${escapeHtml(suggestion.main_text || suggestion.description)}
                ${suggestion.source === 'history' ? '<em class="suggestion-badge">Recent</em>' : ''}
            </strong>
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
    const shell = appShell();
    const lookup = addressAutocomplete[field];
    const input = document.querySelector(`[data-address-input="${field}"]`);
    const placeId = document.querySelector(`[data-address-place-id="${field}"]`);
    const sessionToken = document.querySelector(`[data-address-session-token="${field}"]`);

    input.value = suggestion.description;
    placeId.value = '';
    sessionToken.value = lookup.sessionToken || '';
    hideAddressSuggestions(field);
    setAddressHint(field, 'Validating address...');

    try {
        const payload = await requestJson(shell.dataset.addressValidateUrl, {
            method: 'POST',
            body: JSON.stringify({
                place_id: suggestion.place_id,
                session_token: lookup.sessionToken,
            }),
        });

        input.value = payload.data.formatted_address;
        placeId.value = payload.data.place_id;
        // A green field says the address is valid; no extra text is needed.
        setAddressHint(field, '', 'success');
        lookup.sessionToken = null;
    } catch (error) {
        placeId.value = '';
        setAddressHint(field, error.message, 'error');
    }
}

function hideAddressSuggestions(field) {
    const container = document.querySelector(`[data-address-suggestions="${field}"]`);

    if (!container) {
        return;
    }

    container.hidden = true;
    container.innerHTML = '';
}

function setAddressHint(field, text, type = '') {
    const hint = document.querySelector(`[data-address-hint="${field}"]`);
    const input = document.querySelector(`[data-address-input="${field}"]`);

    if (hint) {
        hint.textContent = text || '';
        hint.dataset.state = type;
    }

    if (input) {
        const state = { success: 'valid', error: 'invalid' }[type] || '';

        input.dataset.addressState = state;
        input.toggleAttribute('aria-invalid', state === 'invalid');
    }
}

function setupRouteFormGuard() {
    const form = document.querySelector('[data-route-form]');

    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        const missing = ['start', 'end'].filter((field) => {
            return !document.querySelector(`[data-address-place-id="${field}"]`)?.value;
        });

        if (!missing.length) {
            return;
        }

        event.preventDefault();

        missing.forEach((field) => {
            setAddressHint(field, 'Select an exact address from the suggestions.', 'error');
        });
    });
}

const ROUTE_STATUS_POLL_MS = 8000;
let routeStatusPolling = false;

function activeRouteCards() {
    return Array.from(document.querySelectorAll('[data-route-card][data-route-active="true"]'));
}

function setupRouteStatusPolling() {
    const shell = appShell();

    if (!shell?.dataset.routeStatusUrl || routeStatusPolling || !activeRouteCards().length) {
        return;
    }

    routeStatusPolling = true;

    window.setTimeout(async function poll() {
        const cards = activeRouteCards();

        if (!cards.length) {
            routeStatusPolling = false;
            return;
        }

        const ids = cards.map((card) => card.dataset.routeId).join(',');

        try {
            const payload = await requestJson(`${shell.dataset.routeStatusUrl}?ids=${encodeURIComponent(ids)}`);
            updateRouteCards(payload.data || []);

            if (!activeRouteCards().length) {
                // Totals, day summary and the Edit/Delete actions depend on the finished
                // distances. Refresh just those parts so whatever is typed in the form stays.
                routeStatusPolling = false;
                await refreshLiveRegions();
                setupRouteStatusPolling();
                return;
            }
        } catch {
            // Try again on the next tick.
        }

        window.setTimeout(poll, ROUTE_STATUS_POLL_MS);
    }, ROUTE_STATUS_POLL_MS);
}

/**
 * Re-renders the parts of the page marked with data-live-region from a fresh copy of the
 * current URL, leaving everything else (forms, focus, scroll position) untouched.
 */
async function refreshLiveRegions() {
    const regions = document.querySelectorAll('[data-live-region]');

    if (!regions.length) {
        return;
    }

    let html;

    try {
        const response = await fetch(window.location.href, {
            headers: { Accept: 'text/html' },
            credentials: 'same-origin',
        });

        if (!response.ok || response.redirected) {
            return;
        }

        html = await response.text();
    } catch {
        return;
    }

    const fresh = new DOMParser().parseFromString(html, 'text/html');

    regions.forEach((region) => {
        const replacement = fresh.querySelector(`[data-live-region="${region.dataset.liveRegion}"]`);

        if (!replacement) {
            return;
        }

        // Swap the content, not the element, so attributes such as aria-live keep working.
        region.innerHTML = replacement.innerHTML;
        setupConfirmForms(region);
    });
}

function updateRouteCards(routes) {
    routes.forEach((route) => {
        const card = document.querySelector(`[data-route-card][data-route-id="${route.id}"]`);

        if (!card) {
            return;
        }

        card.className = card.className
            .split(' ')
            .filter((className) => !className.startsWith('status-'))
            .concat(`status-${route.distance_status || 'pending'}`)
            .join(' ');
        card.dataset.routeActive = route.locked ? 'true' : 'false';

        const statusLabel = card.querySelector('[data-route-status-label]');
        const distanceLabel = card.querySelector('[data-route-distance-label]');
        const error = card.querySelector('[data-route-error]');

        if (statusLabel) {
            statusLabel.textContent = route.distance_status_label || route.distance_status;
        }

        if (distanceLabel) {
            distanceLabel.textContent = route.distance_label;
        }

        if (error) {
            error.textContent = route.distance_error || '';
            error.hidden = !route.distance_error;
        }
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
    setupThemeToggle();
    setupFlashDismissal();
    setupConfirmForms();
    setupAutoSubmit();
    setupReportQuickActions();
    setupAddressAutocomplete();
    setupRouteFormGuard();
    setupRouteStatusPolling();
});
