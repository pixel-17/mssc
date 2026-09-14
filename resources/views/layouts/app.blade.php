<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Sistema de Papeletas') }}</title>

    {{-- PWA: instalable en el celular/escritorio --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2c5480">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Papeletas">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <script>
        // Se aplica ANTES de pintar la página para evitar el parpadeo de tema
        // (flash of wrong theme) al navegar entre páginas server-rendered.
        (function () {
            const guardado = localStorage.getItem('tema');
            const prefiereOscuro = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (guardado === 'oscuro' || (!guardado && prefiereOscuro)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <script>
        window.VAPID_PUBLIC_KEY = "{{ config('webpush.vapid.public_key') }}";

        // Se registra siempre (no solo al activar notificaciones): es lo que
        // hace que el navegador ofrezca "Instalar app". El sw.js sigue
        // manejando el push por separado una vez que el usuario lo active.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-bg font-sans antialiased">

    <x-notification-toast />
    <x-toast-accion />

    @php
        /** @var \App\Models\User $usuario */
        $usuario = Auth::user();
        $esSidebar = $usuario && ($usuario->esAdmin() || $usuario->esRrhh() || $usuario->esJefe());
    @endphp

    @if ($esSidebar)
        @php
            if ($usuario->esAdmin()) {
                $rolLabel = 'Panel de Administración';
                $secciones = [
                    [
                        'label' => 'General',
                        'items' => [
                            ['label' => 'Dashboard', 'route' => route('admin.dashboard'), 'active' => 'admin.dashboard', 'icon' => 'chart'],
                            ['label' => 'Reportes', 'route' => route('reportes.index'), 'active' => 'reportes.*', 'icon' => 'chart'],
                            ['label' => 'Sustentos', 'route' => route('sustentos.index'), 'active' => 'sustentos.*', 'icon' => 'clipboard'],
                            ['label' => 'Papeletas', 'route' => route('papeletas.index'), 'active' => 'papeletas.*', 'icon' => 'document'],
                        ],
                    ],
                    [
                        'label' => 'Catálogos',
                        'items' => [
                            ['label' => 'Usuarios', 'route' => route('users.index'), 'active' => 'users.*', 'icon' => 'users'],
                            ['label' => 'Áreas', 'route' => route('areas.index'), 'active' => 'areas.*', 'icon' => 'building'],
                            ['label' => 'Cargos', 'route' => route('cargos.index'), 'active' => 'cargos.*', 'icon' => 'briefcase'],
                            ['label' => 'Sedes', 'route' => route('sedes.index'), 'active' => 'sedes.*', 'icon' => 'map-pin'],
                            ['label' => 'Motivos', 'route' => route('motivos.index'), 'active' => 'motivos.*', 'icon' => 'clipboard'],
                        ],
                    ],
                    [
                        'label' => 'Sistema',
                        'items' => [
                            ['label' => 'Configuración', 'route' => route('configuracion.edit'), 'active' => 'configuracion.*', 'icon' => 'clock'],
                            ['label' => 'Auditoría', 'route' => route('admin.auditoria'), 'active' => 'admin.auditoria', 'icon' => 'shield'],
                        ],
                    ],
                ];
            } elseif ($usuario->esRrhh()) {
                $pendientesRrhh = \App\Models\Papeleta::pendientesDeRrhh()->count();
                $vistaActual = request()->routeIs('papeletas.index') ? request('vista', 'pendientes') : null;
                $rolLabel = 'Panel de RR. HH.';
                $secciones = [
                    [
                        'label' => 'Papeletas',
                        'items' => [
                            ['label' => 'Por aprobar', 'route' => route('papeletas.index', ['vista' => 'pendientes']), 'active' => $vistaActual === 'pendientes', 'icon' => 'inbox', 'badge' => $pendientesRrhh],
                            ['label' => 'Todas', 'route' => route('papeletas.index', ['vista' => 'todas']), 'active' => $vistaActual === 'todas' || request()->routeIs('papeletas.show'), 'icon' => 'list'],
                        ],
                    ],
                    [
                        'label' => 'Herramientas',
                        'items' => [
                            ['label' => 'Reportes', 'route' => route('reportes.index'), 'active' => 'reportes.*', 'icon' => 'chart'],
                            ['label' => 'Sustentos', 'route' => route('sustentos.index'), 'active' => 'sustentos.*', 'icon' => 'clipboard'],
                        ],
                    ],
                ];
            } else { // JEFE
                $pendientesJefe = \App\Models\Papeleta::pendientesDeJefe($usuario->id)->count();
                $vistaActual = request()->routeIs('papeletas.index') ? request('vista', 'pendientes') : null;
                $rolLabel = 'Panel de Jefe de Área';
                $secciones = [
                    [
                        'label' => 'Mi equipo',
                        'items' => [
                            ['label' => 'Por aprobar', 'route' => route('papeletas.index', ['vista' => 'pendientes']), 'active' => $vistaActual === 'pendientes', 'icon' => 'inbox', 'badge' => $pendientesJefe],
                            ['label' => 'Todas', 'route' => route('papeletas.index', ['vista' => 'todas']), 'active' => $vistaActual === 'todas' || request()->routeIs('papeletas.show'), 'icon' => 'list'],
                            ['label' => 'Mis trabajadores', 'route' => route('equipo.index'), 'active' => 'equipo.*', 'icon' => 'user-circle'],
                            ['label' => 'Reportes', 'route' => route('reportes.index'), 'active' => 'reportes.*', 'icon' => 'chart'],
                            ['label' => 'Sustentos', 'route' => route('sustentos.index'), 'active' => 'sustentos.*', 'icon' => 'clipboard'],
                        ],
                    ],
                ];
            }
        @endphp

        <div class="app-shell" x-data="{ sidebarAbierto: false }">
            @include('layouts.partials.sidebar')

            <div class="flex-1 min-w-0 flex flex-col">
                @include('layouts.partials.topbar')

                <main class="app-content">
                    @if (session('status'))
                        <div id="flash-status" class="glass-card border-l-4 !border-l-emerald-400 text-emerald-700 text-sm p-4 mb-4 animate-fade-in-up flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($usuario->must_change_password && ! $usuario->aviso_password_mostrado)
                        @php
                            $usuario->marcarAvisoPasswordMostrado();
                        @endphp
                        @include('layouts.partials.alerta-cambio-password')
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    @else
        @php
            if ($usuario && $usuario->esVigilante()) {
                $tituloTopbar = 'Control de puerta';
                $navItems = [
                    ['label' => 'Puerta', 'route' => route('vigilancia.index'), 'active' => 'vigilancia.index', 'icon' => 'shield'],
                    ['label' => 'Perfil', 'route' => route('profile.edit'), 'active' => 'profile.edit', 'icon' => 'user-circle'],
                ];
            } else { // TRABAJADOR
                $tituloTopbar = 'Mis papeletas';
                $avisosNoLeidos = $usuario->notificaciones()->where('canal', 'SISTEMA')->whereNull('leida_at')->count();
                $navItems = [
                    ['label' => 'Papeletas', 'route' => route('papeletas.index'), 'active' => ['papeletas.index', 'papeletas.show'], 'icon' => 'document'],
                    ['label' => 'Nueva', 'route' => route('papeletas.create'), 'active' => 'papeletas.create', 'icon' => 'plus-circle'],
                    ['label' => 'Avisos', 'route' => route('notificaciones.index'), 'active' => 'notificaciones.index', 'icon' => 'bell', 'badge' => $avisosNoLeidos],
                    ['label' => 'Perfil', 'route' => route('profile.edit'), 'active' => 'profile.edit', 'icon' => 'user-circle'],
                ];
            }
        @endphp

        @include('layouts.partials.mobile-topbar')

        <main class="mobile-content">
            @if (isset($header))
                <div class="mb-4">{{ $header }}</div>
            @endif

            @if (session('status'))
                <div id="flash-status" class="glass-card border-l-4 !border-l-emerald-400 text-emerald-700 text-sm p-4 mb-4 animate-fade-in-up flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ session('status') }}
                </div>
            @endif

            @if ($usuario && $usuario->must_change_password && ! $usuario->aviso_password_mostrado)
                @php($usuario->marcarAvisoPasswordMostrado())
                @include('layouts.partials.alerta-cambio-password')
            @endif

            {{ $slot }}
        </main>

        @include('layouts.partials.bottom-nav', ['items' => $navItems])
    @endif

    <script src="{{ asset('js/push.js') }}"></script>
    @stack('scripts')
</body>
</html>
