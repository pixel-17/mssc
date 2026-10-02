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
                <p class="font-display font-semibold text-lg leading-tight text-tinta-950 dark:text-white truncate">{{ $titulo }}</p>
            </div>
        @else
            <div class="flex items-center gap-3 min-w-0">
                @if ($usuario)
                    <x-avatar :user="$usuario" size="size-11" class="!text-sm" />
                @endif
                <div class="min-w-0">
                    <p class="text-xs text-tinta-500 dark:text-tinta-300">Hola,</p>
                    <p class="truncate text-lg font-display font-semibold leading-tight text-tinta-950 dark:text-white">
                        {{ $titulo ?: ($usuario ? explode(' ', $usuario->name)[0] : 'Bienvenido') }}
                    </p>
                    @if ($usuario)
                        <x-tipo-usuario :user="$usuario" class="mt-0.5" />
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-1">
        <x-theme-toggle />

        <form method="POST" action="{{ route('logout') }}" class="contents">
            @csrf
            <button type="submit" class="icon-btn" title="Cerrar sesión" aria-label="Cerrar sesión">
                <x-icon name="logout" />
            </button>
        </form>

        <livewire:notification-bell />
    </div>
</header>
