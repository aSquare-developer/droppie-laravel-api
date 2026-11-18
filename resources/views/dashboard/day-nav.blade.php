@php
    $dayUrl = fn (?string $date) => route('dashboard', array_filter(['date' => $date, 'search' => $filters['search']]));
@endphp

<nav class="day-nav" aria-label="Trip dates">
    @if ($day['newer'])
        <a class="ghost-action" href="{{ $dayUrl($day['newer']) }}" rel="prev">&larr; Newer</a>
    @else
        <button type="button" class="ghost-action" disabled>&larr; Newer</button>
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
        <a class="ghost-action" href="{{ $dayUrl($day['older']) }}" rel="next">Older &rarr;</a>
    @else
        <button type="button" class="ghost-action" disabled>Older &rarr;</button>
    @endif
</nav>
