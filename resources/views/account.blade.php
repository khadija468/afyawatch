@extends('layouts.app')

@section('title', 'Account')
@section('noindex', true)

@section('content')
    <div class="container narrow">
        <div class="panel">
            <h1>Account</h1>
            <dl class="definition-list">
                <dt>Name</dt><dd>{{ $user->name }}</dd>
                <dt>Email</dt><dd>{{ $user->email }}</dd>
                <dt>Role</dt><dd>{{ ucfirst($user->role) }}</dd>
                <dt>View</dt><dd>{{ $user->isManager() ? 'All regions' : ($user->region?->name.' region only') }}</dd>
            </dl>

            @if (session('status'))
                <p class="notice notice-success" role="status">{{ session('status') }}</p>
            @endif

            <form method="post" action="{{ route('account') }}">
                @csrf
                @method('put')
                <fieldset class="choice-group">
                    <legend>Alert emails</legend>
                    @foreach ([
                        'immediate' => ['Every alert as it happens', 'Critical and warning alerts are emailed as soon as they are raised.'],
                        'digest' => ['Critical alerts now, the rest once a day', 'Warnings arrive in one summary email at 07:30.'],
                        'off' => ['No alert emails', 'You will still see every alert on the dashboard.'],
                    ] as $value => [$label, $help])
                        <div class="choice">
                            <input type="radio" id="pref-{{ $value }}" name="alert_preference" value="{{ $value }}"
                                   aria-describedby="pref-{{ $value }}-help" @checked(old('alert_preference', $user->alert_preference) === $value)>
                            <label for="pref-{{ $value }}">{{ $label }}</label>
                            <p id="pref-{{ $value }}-help" class="small muted">{{ $help }}</p>
                        </div>
                    @endforeach
                </fieldset>
                <button type="submit" class="button">Save alert setting</button>
            </form>
        </div>
    </div>
@endsection
