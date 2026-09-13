<div x-data>
    {{-- Barra superior — solo móvil/tablet: abre el drawer del sidebar --}}
    <div class="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-white/10 bg-ocean-950 px-4 lg:hidden">
        <button type="button" @click="$store.sidebar.openMobile()" class="icon-chip !bg-white/10 !border-white/20 !text-white" aria-label="Abrir menú">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
        </button>

        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <x-application-mark class="h-7 w-7" />
        </a>

        <div class="flex items-center gap-1">
            <livewire:notification-bell />
        </div>
    </div>

    {{-- Fondo oscuro del drawer en móvil --}}
    <div
        x-show="$store.sidebar.mobileOpen"
        x-cloak
        x-transition.opacity
        @click="$store.sidebar.closeMobile()"
        class="fixed inset-0 z-40 bg-ocean-950/60 backdrop-blur-sm lg:hidden"
    ></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col transition-transform duration-200 ease-in-out lg:translate-x-0"
        :class="[$store.sidebar.mobileOpen ? 'translate-x-0' : '-translate-x-full', $store.sidebar.collapsed ? 'lg:w-20' : 'lg:w-72']"
    >
        <div class="sidebar flex h-full flex-col">
            {{-- Marca --}}
            <div class="flex h-16 shrink-0 items-center gap-3 px-5" :class="$store.sidebar.collapsed && 'lg:justify-center lg:px-0'">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5" :class="$store.sidebar.collapsed && 'lg:justify-center'">
                    <x-application-mark class="h-8 w-8 shrink-0" />
                    <span :class="$store.sidebar.collapsed && 'lg:hidden'" class="min-w-0">
                        <span class="block truncate font-extrabold tracking-tight text-ocean-950 dark:text-white text-sm">MSSC</span>
                        <span class="block truncate text-[11px] font-medium text-gray-500 dark:text-white/60 -mt-0.5">M. de Santiago de Cusco</span>
                    </span>
                </a>

                <button type="button" @click="$store.sidebar.closeMobile()" class="ms-auto icon-chip lg:hidden" aria-label="Cerrar menú">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Navegación --}}
            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                <div class="space-y-1">
                    <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')" :icon="'<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.8\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25\' /></svg>'">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>

                {{--
                    "Jefe" no es un rol de Spatie: cualquier trabajador
                    que encabece una unidad o tenga trabajadores propios
                    (automáticos o adicionales) ve este enlace, sin
                    importar su rol (ver UserPolicy::crearTrabajadorPropio()).
                --}}
                @if (auth()->user()->can('crearTrabajadorPropio', App\Models\User::class) || auth()->user()->hasRole('rrhh'))
                    <div class="space-y-1">
                        <p class="sidebar-section-label" :class="$store.sidebar.collapsed && 'lg:hidden'">{{ __('Gestión') }}</p>

                        @can('crearTrabajadorPropio', App\Models\User::class)
                            <x-nav-link href="{{ route('jefe.papeletas.index') }}" :active="request()->routeIs('jefe.*')" :icon="'<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.8\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z\' /></svg>'">
                                {{ __('Bandeja de Jefe') }}
                            </x-nav-link>
                        @endcan

                        @hasrole('rrhh')
                            <x-nav-link href="{{ route('rrhh.papeletas.index') }}" :active="request()->routeIs('rrhh.*')" :icon="'<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.8\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z\' /></svg>'">
                                {{ __('Papeletas RRHH') }}
                            </x-nav-link>
                        @endhasrole
                    </div>
                @endif

                @if (auth()->user()->hasRole('admin') || auth()->user()->can('puedeCrearAlgo', App\Models\User::class))
                    <div class="space-y-1">
                        <p class="sidebar-section-label" :class="$store.sidebar.collapsed && 'lg:hidden'">{{ __('Administración') }}</p>

                        @hasrole('admin')
                            <x-nav-link href="{{ route('catalogos.index') }}" :active="request()->routeIs('catalogos.*', 'sedes.*')" :icon="'<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.8\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z\' /><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z\' /></svg>'">
                                {{ __('Catálogos') }}
                            </x-nav-link>
                        @endhasrole

                        @can('puedeCrearAlgo', App\Models\User::class)
                            <x-nav-link href="{{ route('usuarios.index') }}" :active="request()->routeIs('usuarios.*')" :icon="'<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.8\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z\' /></svg>'">
                                {{ __('Usuarios') }}
                            </x-nav-link>
                        @endcan
                    </div>
                @endif
            </nav>

            {{-- Pie: equipo, notificaciones, tema y cuenta --}}
            <div class="shrink-0 space-y-1 border-t border-ocean-100 p-3 dark:border-white/10">
                @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
                    <div class="relative">
                        <x-dropdown align="top" width="60">
                            <x-slot name="trigger">
                                <button type="button" class="sidebar-row">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ocean-50 text-ocean-600 dark:bg-white/5 dark:text-ocean-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                                    </span>
                                    <span class="truncate" x-bind:class="$store.sidebar.collapsed && 'lg:hidden'">{{ Auth::user()->currentTeam->name }}</span>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="w-60">
                                    <div class="block px-4 py-2 text-xs font-semibold text-ocean-600 dark:text-ocean-300 uppercase tracking-wide">
                                        {{ __('Manage Team') }}
                                    </div>

                                    <x-dropdown-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">
                                        {{ __('Team Settings') }}
                                    </x-dropdown-link>

                                    @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                                        <x-dropdown-link href="{{ route('teams.create') }}">
                                            {{ __('Create New Team') }}
                                        </x-dropdown-link>
                                    @endcan

                                    @if (Auth::user()->allTeams()->count() > 1)
                                        <div class="border-t border-ocean-100 dark:border-white/10"></div>

                                        <div class="block px-4 py-2 text-xs font-semibold text-ocean-600 dark:text-ocean-300 uppercase tracking-wide">
                                            {{ __('Switch Teams') }}
                                        </div>

                                        @foreach (Auth::user()->allTeams() as $team)
                                            <x-switchable-team :team="$team" />
                                        @endforeach
                                    @endif
                                </div>
                            </x-slot>
                        </x-dropdown>
                    </div>
                @endif

                {{-- Notificaciones (in-app + web push) — visible cuando el sidebar está expandido --}}
                <div class="sidebar-row !cursor-default" :class="$store.sidebar.collapsed && 'lg:hidden'">
                    <span class="flex size-9 shrink-0 items-center justify-center">
                        <livewire:notification-bell />
                    </span>
                    <span>{{ __('Notificaciones') }}</span>
                </div>

                {{-- Modo oscuro: automático según el sistema, con botón para forzarlo --}}
                <button type="button" x-data="msscThemeToggle()" x-init="init()" @click="alternar()" :title="etiqueta" class="sidebar-row">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ocean-50 text-ocean-600 dark:bg-white/5 dark:text-ocean-300">
                        <svg x-show="modo === 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
                        <svg x-show="modo === 'light'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                        <svg x-show="modo === null" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" /></svg>
                    </span>
                    <span x-text="etiqueta" class="truncate text-start" x-bind:class="$store.sidebar.collapsed && 'lg:hidden'"></span>
                </button>

                {{-- Cuenta --}}
                <div class="relative">
                    <x-dropdown align="top" width="60">
                        <x-slot name="trigger">
                            <button type="button" class="sidebar-row">
                                @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                    <img class="size-9 shrink-0 rounded-lg object-cover ring-1 ring-ocean-100 dark:ring-white/10" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                @else
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ocean-600 text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                    </span>
                                @endif

                                <span class="min-w-0 text-start" x-bind:class="$store.sidebar.collapsed && 'lg:hidden'">
                                    <span class="block truncate">{{ Auth::user()->name }}</span>
                                    <span class="block truncate text-xs font-normal text-gray-400 dark:text-ocean-100/50">{{ Auth::user()->email }}</span>
                                </span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="block px-4 py-2 text-xs font-semibold text-ocean-600 dark:text-ocean-300 uppercase tracking-wide">
                                {{ __('Manage Account') }}
                            </div>

                            <x-dropdown-link href="{{ route('profile.show') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <button type="button" x-data="msscPushToggle()" x-init="init()" @click="alternar()" class="flex w-full items-center gap-2 text-start px-4 py-2.5 text-sm font-medium leading-5 text-gray-700 dark:text-ocean-50/90 hover:bg-ocean-50 dark:hover:bg-white/10 hover:text-ocean-800 dark:hover:text-white focus:outline-none transition">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                                <span x-text="etiqueta"></span>
                            </button>

                            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                                    {{ __('API Tokens') }}
                                </x-dropdown-link>
                            @endif

                            <div class="border-t border-ocean-100 dark:border-white/10 my-1"></div>

                            <form method="POST" action="{{ route('logout') }}" x-data>
                                @csrf
                                <x-dropdown-link href="{{ route('logout') }}" class="!text-red-600 hover:!bg-red-50" @click.prevent="$root.submit();">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15" /></svg>
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>

                {{-- Colapsar / expandir — solo escritorio --}}
                <button type="button" @click="$store.sidebar.toggleCollapse()" class="sidebar-row hidden lg:flex" :title="$store.sidebar.collapsed ? 'Expandir menú' : 'Colapsar menú'">
                    <span class="flex size-9 shrink-0 items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5 transition-transform" :class="$store.sidebar.collapsed && 'rotate-180'"><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" /></svg>
                    </span>
                    <span x-bind:class="$store.sidebar.collapsed && 'lg:hidden'">{{ __('Colapsar') }}</span>
                </button>
            </div>
        </div>
    </aside>
</div>
