@extends('layouts.app')

@section('body')
    @php
        $navItems = [
            ['route' => 'dashboard', 'label' => 'Trips'],
            ['route' => 'reports', 'label' => 'Reports'],
            ['route' => 'account', 'label' => 'Account'],
        ];
    @endphp

    <main class="app-shell" @yield('shell_attributes')>
        <header class="topbar">
            <div class="brand-row compact">
                <span class="brand-mark">D</span>
                <span class="brand-name">Droppie</span>
            </div>

            <nav class="main-nav" aria-label="Main">
                @foreach ($navItems as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        @class(['nav-link', 'is-active' => request()->routeIs($item['route'])])
                        @if (request()->routeIs($item['route'])) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="user-block">
                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Theme: follow system" title="Theme: follow system">
                    <svg data-theme-icon="auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 0 16Z" fill="currentColor"/></svg>
                    <svg data-theme-icon="light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" hidden><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <svg data-theme-icon="dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true" hidden><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/></svg>
                    <span data-theme-label>Auto</span>
                </button>
                <span>{{ auth()->user()->profile?->first_name ?? 'User' }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="ghost-action">Log out</button>
                </form>
            </div>
        </header>

        @include('partials.flash')

        @yield('content')
    </main>
@endsection
