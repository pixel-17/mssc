@props(['titulo' => null, 'volverA' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        <link rel="manifest" href="/manifest.json">

        @include('layouts.partials.tema')

        @php
            $tituloPagina = \App\Support\TituloDePagina::resolver($title ?? null, $titulo);
        @endphp
        <title>{{ $tituloPagina ? $tituloPagina.' — ' : '' }}{{ config('app.name', 'MSSC') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="app-bg font-sans antialiased">
        <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-tinta-900 focus:shadow-glass-lg">Saltar al contenido</a>
        <x-banner />

        <div class="min-h-screen bg-institucional flex flex-col">
            @include('layouts.partials.mobile-topbar', ['usuario' => auth()->user()])

            <main id="contenido" tabindex="-1" class="mobile-content focus:outline-none">
                {{ $slot }}
            </main>

            @include('layouts.partials.bottom-nav', ['items' => $navItems])
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
