@extends('layouts.app', ['viteEntries' => ['resources/css/app.css', 'resources/js/dashboard.js']])

@section('title', 'Dashboard')
@section('noindex', true)

@section('content')
    <script type="application/json" id="dashboard-bootstrap">@json($bootstrap)</script>

    <div id="dashboard-app" class="container dashboard">
        <div class="dashboard-heading">
            <h1>Disease surveillance dashboard</h1>
            <p class="muted">Loading {{ $bootstrap['user']['region_name'] ? $bootstrap['user']['region_name'].' region' : 'national' }} figures…</p>
        </div>
        <noscript>
            <p class="notice notice-warning">The dashboard needs JavaScript to draw its charts. The <a href="{{ route('summary') }}">public summary</a> works without it.</p>
        </noscript>
    </div>
@endsection
