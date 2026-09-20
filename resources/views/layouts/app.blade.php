<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">

    @php
        $tituloPagina = \App\Support\TituloDePagina::resolver($title ?? null, null, $header ?? null);
    @endphp
    <title>{{ $tituloPagina ? $tituloPagina.' — ' : '' }}{{ config('app.name', 'MSSC') }}</title>

    {{-- PWA --}}
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Papeletas">


    @include('layouts.partials.tema')

    <script>
        window.VAPID_PUBLIC_KEY = "{{ config('webpush.vapid.public_key') }}";

        // Se registra siempre (no solo al activar notificaciones): es lo que
        // hace que el navegador ofrezca "Instalar app". sw.js sigue manejando
        // el push por separado una vez que el usuario lo active.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-bg font-sans antialiased">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-tinta-900 focus:shadow-glass-lg">Saltar al contenido</a>

    <x-banner />
    <x-notification-toast />

    @if ($usuario && $conSidebar)
        <div class="app-shell" x-data="{ sidebarAbierto: false }" @keydown.escape.window="sidebarAbierto = false">
            <div
                x-show="sidebarAbierto"
                x-cloak
                x-transition.opacity
                @click="sidebarAbierto = false"
                class="sidebar-overlay lg:hidden"
            ></div>

            @include('layouts.partials.sidebar')

            <div class="flex-1 min-w-0 flex flex-col">
                @include('layouts.partials.topbar')

                <main id="contenido" tabindex="-1" class="app-content focus:outline-none">
                    @isset($header)
                        <div class="mb-6">{{ $header }}</div>
                    @endisset

                    @include('layouts.partials.flash')

                    {{ $slot }}
                </main>
            </div>
        </div>
    @else
        @include('layouts.partials.mobile-topbar')

        <main id="contenido" tabindex="-1" class="mobile-content focus:outline-none">
            @isset($header)
                <div class="mb-4">{{ $header }}</div>
            @endisset

            @include('layouts.partials.flash')

            {{ $slot }}
        </main>

        @include('layouts.partials.bottom-nav', ['items' => $navItems])
    @endif

    @stack('modals')
    <script src="{{ asset('js/push.js') }}"></script>
    @stack('scripts')
</body>
</html>
