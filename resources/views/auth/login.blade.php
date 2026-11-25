@extends('layouts.app')

@section('title', 'Sign in')
@section('noindex', true)

@section('content')
    <div class="container narrow">
        <div class="panel auth-panel">
            <h1>Sign in</h1>
            <p class="muted">For authorised health staff. Your session ends after 30 minutes without activity or when the browser closes.</p>

            @if ($errors->any())
                <div class="notice notice-error" role="alert" id="login-errors">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ url('/login') }}" novalidate>
                @csrf
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required
                           @error('email') aria-invalid="true" aria-describedby="login-errors" @enderror>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="button button-block">Sign in</button>
            </form>

            @if (config('afyawatch.show_demo_accounts'))
                <details class="demo-accounts">
                    <summary>Demo accounts</summary>
                    <ul>
                        <li><code>manager@afyawatch.test</code>: national manager, all regions</li>
                        <li><code>kigoma.clinician@afyawatch.test</code>: clinician, Kigoma only</li>
                        <li><code>kagera.clinician@afyawatch.test</code>: clinician, Kagera only</li>
                    </ul>
                    <p class="small">The password is given in the project report.</p>
                </details>
            @endif
        </div>
    </div>
@endsection
