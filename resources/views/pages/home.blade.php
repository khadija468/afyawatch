@extends('layouts.app')

@section('title', 'Weekly disease surveillance for Tanzania Mainland')
@section('description', 'AfyaWatch brings weekly notifiable disease counts, immunization coverage and outbreak alerts for the 26 regions of Tanzania Mainland into one secure dashboard for health managers and clinicians.')

@push('head')
    <meta property="og:type" content="website">
    <meta property="og:title" content="AfyaWatch: weekly disease surveillance for Tanzania Mainland">
    <meta property="og:description" content="One secure view of notifiable diseases, immunization coverage and outbreak alerts for regional health teams.">
    <meta property="og:url" content="{{ route('home') }}">
    <meta name="twitter:card" content="summary">
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'AfyaWatch',
            'url' => route('home'),
            'description' => 'Weekly notifiable disease surveillance dashboard for Tanzania Mainland (capstone prototype using synthetic case data).',
            'inLanguage' => 'en',
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')
    <section class="hero">
        <div class="container">
            <h1>Weekly disease surveillance for Tanzania Mainland</h1>
            <p class="lead">AfyaWatch puts notifiable disease counts, immunization coverage and outbreak alerts for all 26 regions in one place, so regional health teams see a rise in cases in days rather than weeks.</p>
            <p class="hero-actions">
                <a class="button" href="{{ route('login') }}">Sign in to the dashboard</a>
                <a class="button button-secondary" href="{{ route('summary') }}">See this week's public summary</a>
            </p>
        </div>
    </section>

    <div class="container prose-grid">
        <section aria-labelledby="what-heading">
            <h2 id="what-heading">What the dashboard shows</h2>
            <ul>
                <li>Weekly cases of six notifiable conditions, coded with ICD-10 and SNOMED CT.</li>
                <li>Incidence per 100,000 people by region, using 2022 census populations.</li>
                <li>A control chart that separates normal week-to-week variation from a real signal.</li>
                <li>Measles-rubella (MR1) and Penta3 vaccination coverage against the 90% target.</li>
                <li>Reporting completeness, so a fall in cases is not confused with missing reports.</li>
                <li>Email alerts when a threshold is crossed, routed by role and region.</li>
            </ul>
        </section>

        <section aria-labelledby="who-heading">
            <h2 id="who-heading">Who can see what</h2>
            <p><strong>Managers</strong> see every region and can export aggregated figures. <strong>Clinicians</strong> see only their own region. Access is checked on the server for every request, not just hidden in the page.</p>
            <p>Only aggregate counts are stored. Counts from 1 to 4 are flagged on screen and never exported exactly, because small numbers in small areas can identify people.</p>
        </section>

        <section aria-labelledby="data-heading">
            <h2 id="data-heading">Where the data come from</h2>
            <ul>
                <li>Facility weekly reports in the layout of the IDSR weekly form (synthetic for this prototype).</li>
                <li>Regional populations from the 2022 Population and Housing Census, National Bureau of Statistics.</li>
                <li>Reach and engagement from a vaccination campaign's social media accounts (synthetic).</li>
            </ul>
        </section>
    </div>
@endsection
