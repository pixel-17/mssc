{{--
    Barra superior del shell con sidebar. En móvil/tablet es la que abre el
    drawer; en escritorio solo lleva contexto, notificaciones y tema.
    Estilos: .app-topbar y .icon-btn en resources/css/app.css.
--}}
<header class="app-topbar">
    <div class="flex min-w-0 items-center gap-3">
        <button
            type="button"
            @click="sidebarAbierto = true"
            class="icon-btn lg:hidden"
            aria-label="Abrir menú"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        <x-avatar :user="$usuario" size="size-9" class="!text-xs" />
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-tinta-950 dark:text-white">{{ $usuario->name }} {{ $usuario->apellido }}</p>
            <x-tipo-usuario :user="$usuario" class="mt-0.5" />
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-1">
        <x-theme-toggle />

        <livewire:notification-bell />
    </div>
</header>
