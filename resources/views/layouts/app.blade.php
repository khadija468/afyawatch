<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AfyaWatch') · AfyaWatch</title>
    <meta name="description" content="@yield('description', 'AfyaWatch: weekly notifiable disease surveillance for Tanzania Mainland.')">
    @hasSection('noindex')
        <meta name="robots" content="noindex, nofollow">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif
    <meta name="theme-color" content="#0b4f6c">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @stack('head')
    @vite($viteEntries ?? ['resources/css/app.css'])
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>

    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="{{ route('home') }}">
                <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                    <rect width="32" height="32" rx="7"/>
                    <path d="M5 17h6l3-7 4 13 3-6h6" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>AfyaWatch</span>
            </a>
            <nav aria-label="Main">
                <ul class="nav-list">
                    <li><a href="{{ route('summary') }}" @if (request()->routeIs('summary')) aria-current="page" @endif>Public summary</a></li>
                    @auth
                        <li><a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a></li>
                        <li><a href="{{ route('account') }}" @if (request()->routeIs('account')) aria-current="page" @endif>Account</a></li>
                        <li>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="link-button">Sign out</button>
                            </form>
                        </li>
                    @else
                        <li><a class="button button-small" href="{{ route('login') }}">Sign in</a></li>
                    @endauth
                </ul>
            </nav>
        </div>
    </header>

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>AfyaWatch is a capstone prototype. Case and campaign data are <strong>synthetic</strong>; population figures come from the 2022 Population and Housing Census (National Bureau of Statistics, Tanzania).</p>
        </div>
    </footer>
</body>
</html>
