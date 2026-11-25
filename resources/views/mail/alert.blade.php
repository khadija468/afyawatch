<x-mail::message>
# {{ ucfirst($alert->severity) }} alert: {{ $alert->condition->name }} in {{ $alert->region->name }}

**{{ $alert->observed }}** {{ $alert->condition->case_basis }} cases were reported in {{ $alert->region->name }} region in week {{ $weekLabel }}.

<x-mail::table>
| | |
|:--|--:|
| Condition | {{ $alert->condition->name }} (ICD-10 {{ $alert->condition->icd10_code }}) |
| Cases this week | {{ $alert->observed }} |
@if ($alert->expected !== null)
| Expected (8-week mean) | {{ number_format($alert->expected, 1) }} |
@endif
| Alert threshold | {{ number_format($alert->limit_value, 1) }} |
| Region population (2022) | {{ number_format($alert->region->population_2022) }} |
</x-mail::table>

Open the dashboard to see the trend, the facilities reporting and whether anyone has already acknowledged this alert.

<x-mail::button :url="$dashboardUrl">
Open AfyaWatch
</x-mail::button>

This email contains aggregate counts only. You receive it because of your role and region; you can switch to a daily digest in your account settings, or ask a manager to change it.

{{ config('app.name') }}
</x-mail::message>
