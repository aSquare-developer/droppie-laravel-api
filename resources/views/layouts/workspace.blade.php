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
                <div>
                    <span class="brand-name">Droppie</span>
                    <span class="brand-subtitle">Web dashboard</span>
                </div>
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
