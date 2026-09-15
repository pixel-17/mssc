{{--
    Sidebar institucional (ocean) para los roles que gestionan a otros.
    Recibe $secciones y $rolLabel armados en layouts/app.blade.php, y
    comparte el x-data del shell (sidebarAbierto) para el drawer en móvil.
    Los estilos viven en resources/css/app.css: .app-sidebar, .sidebar-link,
    .sidebar-badge, etc.
--}}
<aside class="app-sidebar" :class="sidebarAbierto && 'is-open'">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
            <x-application-mark class="h-9 w-9 shrink-0" />
            <span class="min-w-0">
                <span class="block truncate text-sm font-extrabold tracking-tight text-white">MSSC</span>
                <span class="-mt-0.5 block truncate text-[11px] font-medium text-ocean-200">{{ $rolLabel }}</span>
            </span>
        </a>

        <button
            type="button"
            @click="sidebarAbierto = false"
            class="ms-auto rounded-lg p-1.5 text-ocean-200 hover:bg-white/10 hover:text-white lg:hidden"
            aria-label="Cerrar menú"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="Navegación principal">
        @foreach ($secciones as $seccion)
            @if ($seccion['label'])
                <p class="sidebar-section-label">{{ $seccion['label'] }}</p>
            @endif

            <div class="space-y-0.5">
                @foreach ($seccion['items'] as $item)
                    <a
                        href="{{ $item['route'] }}"
                        @class(['sidebar-link', 'is-active' => $item['active']])
                        @if ($item['active']) aria-current="page" @endif
                    >
                        <x-icon :name="$item['icon']" class="sidebar-icon" />
                        <span class="truncate">{{ $item['label'] }}</span>

                        @if (($item['badge'] ?? 0) > 0)
                            <span class="sidebar-badge">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <a
            href="{{ route('profile.show') }}"
            @class(['sidebar-link', 'is-active' => request()->routeIs('profile.show')])
        >
            <x-icon name="user-circle" class="sidebar-icon" />
            <span class="truncate">Mi perfil</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link w-full">
                <x-icon name="logout" class="sidebar-icon" />
                <span class="truncate">Cerrar sesión</span>
            </button>
        </form>
    </div>
</aside>
