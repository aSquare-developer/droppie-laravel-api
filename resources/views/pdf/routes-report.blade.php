<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trip Log</title>

    <style>
        @page {
            margin: 24px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }

        h1 {
            text-align: center;
            margin: 0 0 4px;
            font-size: 20px;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 16px;
            color: #555;
        }

        .summary {
            width: 100%;
            margin-bottom: 16px;
            border: 1px solid #999;
            border-collapse: collapse;
        }

        .summary td {
            width: 25%;
            padding: 6px;
            border: 0;
        }

        .summary-label {
            display: block;
            margin-bottom: 2px;
            color: #666;
            font-size: 8px;
            text-transform: uppercase;
        }

        .routes {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .routes th,
        .routes td {
            border: 1px solid #999;
            padding: 5px;
            vertical-align: top;
            overflow-wrap: break-word;
        }

        .routes th {
            background: #eee;
            font-size: 8px;
            text-align: left;
        }

        .date {
            width: 12%;
        }

        .address {
            width: 42%;
        }

        .reading {
            width: 14%;
            text-align: right;
        }

        .distance {
            width: 14%;
            text-align: right;
        }

        .total {
            margin-top: 12px;
            font-size: 11px;
            font-weight: bold;
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Trip Log</h1>

    <div class="subtitle">
        Period: {{ $from }} &ndash; {{ $to }}
    </div>

    <table class="summary">
        <tr>
            <td>
                <span class="summary-label">Driver</span>
                <strong>{{ $user->name }} {{ $user->last_name }}</strong><br>
                {{ $user->email }}@if ($user->country) &middot; {{ $user->country }}@endif
            </td>
            <td>
                <span class="summary-label">Company</span>
                {{ $user->company_name ?: 'Not specified' }}
            </td>
            <td>
                <span class="summary-label">Vehicle</span>
                <strong>{{ $user->car_registration_number }}</strong><br>
                {{ $user->car_make_model ?: 'Make and model not specified' }}
            </td>
            <td>
                <span class="summary-label">Summary</span>
                {{ $tripLogRows->count() }} trips<br>
                {{ number_format($totalDistanceKm, 1, '.', ' ') }} km
            </td>
        </tr>
    </table>

    <table class="routes">
        <thead>
            <tr>
                <th class="date">Date</th>
                <th class="address">Departure and destination (business trip purpose)</th>
                <th class="reading">Odometer start, km</th>
                <th class="reading">Odometer end, km</th>
                <th class="distance">Route distance, km</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tripLogRows as $row)
                <tr>
                    <td class="date">{{ $row['route']->started_at->format('d.m.Y') }}</td>
                    <td class="address">
                        {{ $row['route']->startAddress?->formatted_address }}<br>
                        &rarr; {{ $row['route']->endAddress?->formatted_address }}
                    </td>
                    <td class="reading">{{ number_format($row['odometer_start_km'], 1, '.', ' ') }}</td>
                    <td class="reading">{{ number_format($row['odometer_end_km'], 1, '.', ' ') }}</td>
                    <td class="distance">{{ number_format($row['route']->distance_km, 1, '.', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No completed routes found for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">
        Total: {{ number_format($totalDistanceKm, 1, '.', ' ') }} km
    </div>
</body>
</html>
