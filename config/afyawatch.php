<?php

return [

    // True while the case data are synthetic. Public pages then carry a warning banner and the
    // public summary is kept out of search engines, so invented figures cannot pass as real ones.
    'synthetic_data' => (bool) env('AFYAWATCH_SYNTHETIC_DATA', true),

    // Show the demo account names on the sign-in page (useful for markers and usability testers).
    'show_demo_accounts' => (bool) env('AFYAWATCH_SHOW_DEMO_ACCOUNTS', false),

    // Condition shown when the dashboard first opens (ICD-10 code).
    'default_condition' => env('AFYAWATCH_DEFAULT_CONDITION', 'B05'),

    // Share of the population assumed to be surviving infants (immunization denominator).
    'surviving_infant_share' => 0.035,

    // Programme targets, in percent.
    'coverage_target' => 90,
    'completeness_target' => 80,

    // After an alert email, the same condition and region is not emailed again for this many
    // days unless the alert escalates to critical or the case count at least doubles.
    'alert_cooldown_days' => 28,

    // Base URL used for FHIR canonical references.
    'fhir_base' => env('AFYAWATCH_FHIR_BASE', rtrim(env('APP_URL', 'http://localhost'), '/').'/api/fhir'),
];
