@props(['titulo' => null, 'volverA' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        <meta name="theme-color" content="#0a2c4d">

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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        <div class="min-h-screen bg-institucional flex flex-col">
            <!-- Barra superior -->
            <header class="glass-nav sticky top-0 z-20">
                <div class="max-w-md mx-auto w-full px-4 pt-[max(env(safe-area-inset-top),1rem)] pb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($volverA)
                            <a href="{{ $volverA }}" class="icon-chip !bg-white/10 !border-white/20 !text-white shrink-0" aria-label="Volver">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                            </a>
                            <p class="font-bold text-lg leading-tight text-white truncate">{{ $titulo }}</p>
                        @else
                            <div class="min-w-0">
                                <p class="text-xs text-white/70">Hola,</p>
                                <p class="font-bold text-lg leading-tight text-white truncate">{{ $titulo ?? explode(' ', auth()->user()->name)[0] }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            x-data="msscThemeToggle()"
                            x-init="init()"
                            @click="alternar()"
                            :title="etiqueta"
                            class="icon-chip !bg-white/10 !border-white/20 !text-white hover:!bg-white/20 transition"
                        >
                            <svg x-show="modo === 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
                            <svg x-show="modo === 'light'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                            <svg x-show="modo === null" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" /></svg>
                        </button>

                        <livewire:notification-bell />
                    </div>
                </div>
            </header>

            <!-- Contenido -->
            <main class="flex-1 max-w-md mx-auto w-full px-4 py-5 pb-32">
                {{ $slot }}
            </main>

            <!-- Barra de navegación inferior -->
            <nav class="fixed bottom-0 inset-x-0 z-20">
                <div class="max-w-md mx-auto w-full px-6 pb-[max(env(safe-area-inset-bottom),1rem)] pt-2">
                    <div class="glass-strong rounded-[2rem] px-6 py-3 flex items-center justify-between">
                        <a
                            href="{{ route('trabajador.papeletas.index') }}"
                            class="flex flex-col items-center gap-1 px-3 py-1 text-[11px] font-semibold transition {{ request()->routeIs('trabajador.papeletas.*') ? 'text-ocean-600 dark:text-ocean-300' : 'text-gray-400 dark:text-white/40 hover:text-gray-600 dark:hover:text-white/70' }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12c0-1.5.5-2 2-2 1.24 0 1.5-.67 1.5-1.5C5.75 6.5 6.5 5.25 8 5.25h8c1.5 0 2.25 1.25 2.25 3.25 0 .83.26 1.5 1.5 1.5 1.5 0 2 .5 2 2s-.5 2-2 2c-1.24 0-1.5.67-1.5 1.5 0 2-.75 3.25-2.25 3.25H8c-1.5 0-2.25-1.25-2.25-3.25 0-.83-.26-1.5-1.5-1.5-1.5 0-2-.5-2-2z" /></svg>
                            Mis papeletas
                        </a>

                        <a
                            href="{{ route('trabajador.papeletas.create') }}"
                            class="-mt-9 flex items-center justify-center size-14 rounded-full bg-gradient-to-br from-ocean-500 to-ocean-700 text-white shadow-ocean-glow ring-[6px] ring-ocean-50 dark:ring-ocean-950 hover:scale-105 active:scale-95 transition-transform"
                            aria-label="Nueva papeleta"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </a>

                        <a
                            href="{{ route('profile.show') }}"
                            class="flex flex-col items-center gap-1 px-3 py-1 text-[11px] font-semibold transition {{ request()->routeIs('profile.show') ? 'text-ocean-600 dark:text-ocean-300' : 'text-gray-400 dark:text-white/40 hover:text-gray-600 dark:hover:text-white/70' }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            Perfil
                        </a>
                    </div>
                </div>
            </nav>
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
