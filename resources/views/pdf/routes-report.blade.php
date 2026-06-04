<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Routes Report</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
        }

        h1 {
            text-align: center;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 25px;
        }

        .summary {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #999;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #eee;
        }

        .total {
            margin-top: 20px;
            font-size: 14px;
            font-weight: bold;
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Routes Report</h1>

    <div class="subtitle">
        Period: {{ $from }} — {{ $to }}
    </div>

    <div class="summary">
        <strong>User:</strong> {{ $user->name }}<br>
        <strong>Email:</strong> {{ $user->email }}<br>
        <strong>Total routes:</strong> {{ $routes->count() }}<br>
        <strong>Total distance:</strong> {{ $totalDistanceKm }} km
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Start</th>
                <th>End</th>
                <th>Distance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($routes as $route)
                <tr>
                    <td>{{ $route->started_at->format('d.m.Y') }}</td>
                    <td>{{ $route->startAddress?->formatted_address }}</td>
                    <td>{{ $route->endAddress?->formatted_address }}</td>
                    <td>{{ $route->distance_km }} km</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No completed routes found for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">
        Total: {{ $totalDistanceKm }} km
    </div>
</body>
</html>
