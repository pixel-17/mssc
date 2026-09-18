@props(['titulo' => null, 'volverA' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        <meta name="theme-color" content="#0f1c2e">
        <link rel="manifest" href="/manifest.json">

        {{-- Anti-parpadeo de modo oscuro — ver resources/js/theme.js para el toggle en vivo. --}}
        <script>
            (function () {
                var modo = localStorage.getItem('mssc-theme');
                var oscuro = modo === 'dark' || (modo === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', oscuro);
            })();
        </script>

        <title>{{ $titulo ? $titulo.' — ' : '' }}{{ config('app.name', 'MSSC') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        <div class="min-h-screen bg-institucional flex flex-col">
            @php
                $navItems = [
                    ['label' => 'Papeletas', 'route' => route('trabajador.papeletas.index'), 'active' => request()->routeIs('trabajador.papeletas.index', 'trabajador.papeletas.show'), 'icon' => 'document'],
                    ['label' => 'Nueva', 'route' => route('trabajador.papeletas.create'), 'active' => request()->routeIs('trabajador.papeletas.create'), 'icon' => 'plus-circle'],
                    ['label' => 'Calendario', 'route' => route('turnos.calendario.individual'), 'active' => request()->routeIs('turnos.calendario.individual'), 'icon' => 'calendar'],
                    ['label' => 'Perfil', 'route' => route('profile.show'), 'active' => request()->routeIs('profile.show'), 'icon' => 'user-circle'],
                ];
            @endphp

            @include('layouts.partials.mobile-topbar', ['usuario' => auth()->user()])

            <main class="mobile-content">
                {{ $slot }}
            </main>

            @include('layouts.partials.bottom-nav', ['items' => $navItems])
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
