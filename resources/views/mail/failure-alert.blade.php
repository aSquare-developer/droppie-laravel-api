A background job failed on {{ config('app.name') }} ({{ config('app.env') }}, {{ config('app.url') }}).

Job: {{ $source }}
Error: {{ class_basename($exception) }}: {{ $exception->getMessage() }}
Time: {{ now()->toDateTimeString() }} {{ config('app.timezone') }}
@foreach ($context as $name => $value)
{{ ucfirst(str_replace('_', ' ', $name)) }}: {{ is_scalar($value) ? $value : json_encode($value) }}
@endforeach
@if ($suppressed > 0)

{{ $suppressed }} similar {{ \Illuminate\Support\Str::plural('failure', $suppressed) }} happened since the previous alert.
@endif

Further failures of this kind are not emailed for {{ $throttleMinutes }} minutes. Details are in storage/logs and the failed_jobs table.
