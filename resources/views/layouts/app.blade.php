<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">

        {{-- Apply a theme picked in the top bar before the page paints, so it never flashes. --}}
        <script>
            try {
                const theme = localStorage.getItem('droppie-theme');
                if (theme === 'light' || theme === 'dark') {
                    document.documentElement.dataset.theme = theme;
                }
            } catch (error) {}
        </script>

        <title>@yield('title', 'Droppie')</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @yield('body')
    </body>
</html>
