<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('layouts.partials.tema')

        @php
            $tituloPagina = \App\Support\TituloDePagina::resolver($title ?? null) ?? match (true) {
                request()->routeIs('login') => 'Iniciar sesión',
                request()->routeIs('password.request') => 'Recuperar contraseña',
                request()->routeIs('password.reset') => 'Restablecer contraseña',
                request()->routeIs('two-factor.login') => 'Verificación en dos pasos',
                request()->routeIs('password.confirm') => 'Confirmar contraseña',
                default => null,
            };
        @endphp
        <title>{{ $tituloPagina ? $tituloPagina.' — ' : '' }}{{ config('app.name', 'MSSC') }}</title>


        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="font-sans text-gray-900 dark:text-white antialiased">
        <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-tinta-900 focus:shadow-glass-lg">Saltar al contenido</a>
        <main id="contenido" tabindex="-1" class="focus:outline-none">
        {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
