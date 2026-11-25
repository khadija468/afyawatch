<x-mail::message>
# Open alerts from the last 24 hours

<x-mail::table>
| Week | Condition | Region | Cases | Severity |
|:--|:--|:--|--:|:--|
@foreach ($alerts as $alert)
| {{ \App\Support\Week::label($alert->week_start) }} | {{ $alert->condition->name }} | {{ $alert->region->name }} | {{ $alert->observed }} | {{ ucfirst($alert->severity) }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$dashboardUrl">
Review alerts
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
