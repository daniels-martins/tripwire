<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="min-h-screen antialiased">
        @include('partials.nav')
        {{ $slot }}
        @include('partials.footer')
        @fluxScripts
        @stack('scripts')
    </body>
</html>
