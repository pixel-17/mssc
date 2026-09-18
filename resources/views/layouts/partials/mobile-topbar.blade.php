{{--
    Barra superior del shell móvil. Dos modos:
    - Saludo (por defecto): "Hola, <nombre>" — usado por layouts.app cuando
      el usuario no tiene sidebar (trabajador sin gente a cargo).
    - Volver + título: se activa pasando $volverA (URL) y $titulo — usado
      por components/trabajador-layout.blade.php en pantallas internas
      (ej. "Nueva papeleta"). Mismo componente para que las dos entradas al
      sistema se sientan iguales. Estilos: .mobile-topbar en app.css.
--}}
@php
    $volverA ??= null;
    $titulo ??= null;
@endphp

<header class="mobile-topbar">
    <div class="min-w-0">
        @if ($volverA)
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ $volverA }}" class="icon-btn shrink-0" aria-label="Volver">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                </a>
                <p class="font-display font-semibold text-lg leading-tight text-ocean-950 dark:text-white truncate">{{ $titulo }}</p>
            </div>
        @else
            <p class="text-xs text-ocean-500 dark:text-ocean-300">Hola,</p>
            <p class="truncate text-lg font-display font-semibold leading-tight text-ocean-950 dark:text-white">
                {{ $titulo ?: ($usuario ? explode(' ', $usuario->name)[0] : 'Bienvenido') }}
            </p>
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-1">
        <button
            type="button"
            x-data="msscThemeToggle()"
            x-init="init()"
            @click="alternar()"
            :title="etiqueta"
            :aria-label="etiqueta"
            class="icon-btn"
        >
            <svg x-show="modo === 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
            <svg x-show="modo === 'light'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
            <svg x-show="modo === null" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" /></svg>
        </button>

        <form method="POST" action="{{ route('logout') }}" class="contents">
            @csrf
            <button type="submit" class="icon-btn" title="Cerrar sesión" aria-label="Cerrar sesión">
                <x-icon name="logout" />
            </button>
        </form>

        <livewire:notification-bell />
    </div>
</header>
