@php
    $status = $route->distance_status ?: 'pending';
    $locked = $route->isDistanceCalculationInProgress();
@endphp

<article
    class="route-card status-{{ $status }}"
    data-route-card
    data-route-id="{{ $route->id }}"
    data-route-active="{{ $locked ? 'true' : 'false' }}"
>
    <div class="route-card-main">
        <div class="route-path">
            <span class="path-dot"></span>
            <div>
                <strong>{{ $route->startAddress?->formatted_address }}</strong>
                <span>{{ $route->endAddress?->formatted_address }}</span>
            </div>
        </div>
        <div class="route-meta">
            <span class="status-pill" data-route-status-label>{{ $statusLabels[$status] ?? $status }}</span>
            <strong data-route-distance-label>{{ $distanceLabel($route->distance_km) }}</strong>
            <small>{{ $dateLabel($route->started_at) }}</small>
        </div>
    </div>

    <p class="route-note" data-route-error @hidden(blank($route->distance_error))>{{ $route->distance_error }}</p>

    <div class="route-actions">
        @if ($locked)
            <button type="button" class="text-action" disabled title="Available after distance calculation finishes">Edit</button>
            <button type="button" class="danger-action" disabled title="Available after distance calculation finishes">Delete</button>
        @else
            <a class="text-action" href="{{ route('dashboard', [...request()->query(), 'edit' => $route->id]) }}">Edit</a>
            <form method="POST" action="{{ route('web.routes.destroy', $route) }}" data-confirm="Delete this route?">
                @csrf
                @method('DELETE')
                <button type="submit" class="danger-action">Delete</button>
            </form>
        @endif
    </div>
</article>
