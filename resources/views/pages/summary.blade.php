@extends('layouts.app')

@php
    $synthetic = config('afyawatch.synthetic_data');
    $shareText = "AfyaWatch weekly summary for Tanzania Mainland, {$summary['week_label']}";
    $shareUrl = route('summary');
@endphp

@section('title', "Public summary {$summary['week_label']}")
@section('description', "Notifiable disease counts for Tanzania Mainland, week {$summary['week_label']} ({$summary['week']->format('j M')} to {$summary['week_end']->format('j M Y')}).".($synthetic ? ' Demonstration figures, not real surveillance data.' : ''))

{{-- While the figures are synthetic this page must not be indexed: invented outbreak numbers
     appearing in search results would be health misinformation. --}}
@if ($synthetic)
    @section('noindex', true)
@endif

@push('head')
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $shareText }}">
    <meta property="og:description" content="{{ $synthetic ? 'Demonstration figures, not real surveillance data.' : 'Weekly notifiable disease counts from AfyaWatch.' }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta name="twitter:card" content="summary">
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => "AfyaWatch weekly notifiable disease summary, {$summary['week_label']}",
            'description' => 'National totals of six notifiable conditions for Tanzania Mainland for one ISO week.'.($synthetic ? ' Synthetic demonstration data.' : ''),
            'url' => $shareUrl,
            'temporalCoverage' => $summary['week']->toDateString().'/'.$summary['week_end']->toDateString(),
            'spatialCoverage' => ['@type' => 'Place', 'name' => 'Tanzania Mainland'],
            'variableMeasured' => collect($summary['conditions'])->pluck('name')->all(),
            'isAccessibleForFree' => true,
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'creator' => ['@type' => 'Organization', 'name' => 'AfyaWatch'],
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')
    <div class="container narrow">
        @if ($synthetic)
            <p class="notice notice-warning" role="note"><strong>Demonstration data.</strong> These figures are synthetic and do not describe real disease in Tanzania. For official figures, see the Ministry of Health.</p>
        @endif

        <article class="summary-card" aria-labelledby="summary-heading">
            <header>
                <p class="eyebrow">Tanzania Mainland · week {{ $summary['week_label'] }}</p>
                <h1 id="summary-heading">Notifiable diseases this week</h1>
                <p class="muted">{{ $summary['week']->format('l j F') }} to {{ $summary['week_end']->format('l j F Y') }}. {{ $summary['completeness_pct'] }}% of facilities reported.</p>
            </header>

            <table class="data-table">
                <caption class="visually-hidden">Reported cases by condition, national total</caption>
                <thead>
                    <tr><th scope="col">Condition</th><th scope="col">ICD-10</th><th scope="col" class="num">Cases</th></tr>
                </thead>
                <tbody>
                    @foreach ($summary['conditions'] as $condition)
                        <tr>
                            <th scope="row">{{ $condition['name'] }}</th>
                            <td>{{ $condition['icd10'] }}</td>
                            <td class="num">{{ $condition['display'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($campaign)
                <p class="muted">Measles-rubella catch-up campaign: {{ number_format($campaign['reach']) }} accounts reached on social media, {{ $campaign['engagement_rate_pct'] }}% engaged.</p>
            @endif

            <footer class="share">
                <h2 class="share-heading">Share this summary</h2>
                <ul class="share-list">
                    <li><a class="button button-secondary button-small" href="https://wa.me/?text={{ rawurlencode($shareText.' '.$shareUrl) }}" rel="noopener" target="_blank">WhatsApp<span class="visually-hidden"> (opens in a new tab)</span></a></li>
                    <li><a class="button button-secondary button-small" href="https://x.com/intent/post?text={{ rawurlencode($shareText) }}&amp;url={{ rawurlencode($shareUrl) }}" rel="noopener" target="_blank">X<span class="visually-hidden"> (opens in a new tab)</span></a></li>
                    <li><a class="button button-secondary button-small" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" rel="noopener" target="_blank">Facebook<span class="visually-hidden"> (opens in a new tab)</span></a></li>
                </ul>
                <p class="muted small">Plain links, no tracking scripts: the platforms learn nothing until you choose to share. Counts below 5 are shown as "fewer than 5".</p>
            </footer>
        </article>
    </div>
@endsection
