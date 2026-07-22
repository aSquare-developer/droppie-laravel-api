@extends('layouts.app')

@section('title', 'Register - Droppie')

@section('body')
    <main class="auth-shell">
        <section class="auth-visual-panel" aria-label="Droppie">
            <div class="brand-row">
                <span class="brand-mark">D</span>
                <span class="brand-name">Droppie</span>
            </div>
            <div class="route-visual" aria-hidden="true">
                <span class="route-stop route-stop-start"></span>
                <span class="route-line"></span>
                <span class="route-pin">24.8</span>
                <span class="route-stop route-stop-end"></span>
            </div>
            <div class="auth-copy">
                <h1>Route management dashboard</h1>
                <p>Manage addresses, distances, and reports in one web interface.</p>
            </div>
        </section>

        <section class="auth-card" aria-label="Authentication">
            <div class="mobile-brand">
                <span class="brand-mark">D</span>
                <span class="brand-name">Droppie</span>
            </div>

            @include('partials.flash')

            <div class="segmented" role="tablist" aria-label="Authentication mode">
                <a href="{{ route('login') }}">Sign in</a>
                <a class="active" href="{{ route('register') }}">Register</a>
            </div>

            @include('partials.google-auth')

            <form method="POST" action="{{ route('register.store') }}" class="stacked-form">
                @csrf
                <label>
                    <span>Name</span>
                    <input name="name" type="text" autocomplete="name" value="{{ old('name') }}" required>
                </label>
                <label>
                    <span>Email</span>
                    <input name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>
                </label>
                <label>
                    <span>Password</span>
                    <input name="password" type="password" autocomplete="new-password" required minlength="6">
                </label>
                <button class="primary-action" type="submit">Create account</button>
            </form>
        </section>
    </main>
@endsection
