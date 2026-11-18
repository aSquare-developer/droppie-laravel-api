@php
    $dayUrl = fn (?string $date) => route('dashboard', array_filter(['date' => $date, 'search' => $filters['search']]));
@endphp

<nav class="day-nav" aria-label="Trip dates">
    @if ($day['newer'])
        <a class="ghost-action" href="{{ $dayUrl($day['newer']) }}" rel="prev" aria-label="Newer date">&larr; <span class="nav-label">Newer</span></a>
    @else
        <button type="button" class="ghost-action" disabled aria-label="Newer date">&larr; <span class="nav-label">Newer</span></button>
    @endif

    @if ($withPicker ?? false)
        <form method="GET" action="{{ route('dashboard') }}" class="day-picker">
            @if ($filters['search'] !== '')
                <input type="hidden" name="search" value="{{ $filters['search'] }}">
            @endif
            <label>
                <span class="sr-only">Go to date</span>
                <input name="date" type="date" value="{{ $day['date']?->toDateString() }}" data-auto-submit>
            </label>
        </form>
    @elseif ($day['position'])
        <span class="day-position">{{ $day['position'] }} / {{ $day['count'] }}</span>
    @endif

    @if ($day['older'])
        <a class="ghost-action" href="{{ $dayUrl($day['older']) }}" rel="next" aria-label="Older date"><span class="nav-label">Older</span> &rarr;</a>
    @else
        <button type="button" class="ghost-action" disabled aria-label="Older date"><span class="nav-label">Older</span> &rarr;</button>
    @endif
</nav>
