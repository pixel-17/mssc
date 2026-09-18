<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">

    <title>{{ config('app.name', 'MSSC') }}</title>

    {{-- PWA --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2c5480">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Papeletas">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:500,600,700,800&display=swap" rel="stylesheet" />

    {{--
        Anti-parpadeo de tema: se aplica ANTES de pintar. La clave y los
        valores tienen que coincidir con resources/js/theme.js ('mssc-theme',
        'light' | 'dark' | ausente = automático) y con el mismo bloque de
        components/trabajador-layout.blade.php.
    --}}
    <script>
        (function () {
            var modo = localStorage.getItem('mssc-theme');
            var oscuro = modo === 'dark' || (modo === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', oscuro);
        })();
    </script>

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

    <x-banner />
    <x-notification-toast />

    @php
        /** @var \App\Models\User|null $usuario */
        $usuario = Auth::user();

        $esAdmin = $usuario?->hasRole('admin') ?? false;
        $esRrhh = $usuario?->hasRole('rrhh') ?? false;
        $esJefe = $usuario ? $usuario->can('crearTrabajadorPropio', \App\Models\User::class) : false;
        $puedeAltaUsuarios = $usuario ? $usuario->can('puedeCrearAlgo', \App\Models\User::class) : false;

        // El shell con sidebar es para quien gestiona a otros. El trabajador
        // sin gente a cargo usa el shell móvil de abajo — mismo criterio que
        // components/trabajador-layout.blade.php.
        $conSidebar = $esAdmin || $esRrhh || $esJefe;
    @endphp

    @if ($usuario && $conSidebar)
        @php
            $rolLabel = $esAdmin ? 'Administración' : ($esRrhh ? 'Recursos Humanos' : 'Jefatura');

            $pendientesJefe = $esJefe
                ? \App\Models\Papeleta::where('estado', \App\States\Papeleta\PendienteJefe::class)
                    ->where(fn ($q) => $q->where('jefe_inmediato_id', $usuario->id)->orWhere('jefe_area_id', $usuario->id))
                    ->count()
                : 0;

            $pendientesRrhh = $esRrhh
                ? \App\Models\Papeleta::where('estado', \App\States\Papeleta\PendienteRrhh::class)->count()
                : 0;

            $secciones = [[
                'label' => null,
                'items' => [
                    ['label' => 'Inicio', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'home'],
                ],
            ]];

            $bandejas = [];

            if ($esJefe) {
                $bandejas[] = ['label' => 'Papeletas de mi equipo', 'route' => route('jefe.papeletas.index'), 'active' => request()->routeIs('jefe.*'), 'icon' => 'inbox', 'badge' => $pendientesJefe];
            }

            if ($esRrhh) {
                $bandejas[] = ['label' => 'Papeletas de RR. HH.', 'route' => route('rrhh.papeletas.index'), 'active' => request()->routeIs('rrhh.*'), 'icon' => 'clipboard', 'badge' => $pendientesRrhh];
            }

            if ($usuario->hasRole('trabajador')) {
                $bandejas[] = ['label' => 'Mis papeletas', 'route' => route('trabajador.papeletas.index'), 'active' => request()->routeIs('trabajador.*'), 'icon' => 'document'];
            }

            if ($bandejas) {
                $secciones[] = ['label' => 'Bandejas', 'items' => $bandejas];
            }

            $turnosJefe = [];

            if ($esJefe) {
                $turnosJefe[] = ['label' => 'Calendario del equipo', 'route' => route('turnos.calendario.equipo'), 'active' => request()->routeIs('turnos.calendario.equipo'), 'icon' => 'calendar'];
                $turnosJefe[] = ['label' => 'Programar horarios', 'route' => route('turnos.programacion.equipo'), 'active' => request()->routeIs('turnos.programacion.*'), 'icon' => 'clock'];
            }

            if ($turnosJefe) {
                $secciones[] = ['label' => 'Turnos', 'items' => $turnosJefe];
            }

            // Reportes: mismo criterio de acceso que las rutas (admin, RR. HH. o
            // jefe). Cada componente ya recorta los datos al alcance del usuario.
            $reportes = [
                ['label' => 'Horas acumuladas', 'route' => route('reportes.horas-acumuladas'), 'active' => request()->routeIs('reportes.horas-acumuladas'), 'icon' => 'chart'],
                ['label' => 'Ficha de trabajador', 'route' => route('reportes.trabajador-historial'), 'active' => request()->routeIs('reportes.trabajador-historial'), 'icon' => 'user-circle'],
                ['label' => 'Sustentos', 'route' => route('reportes.sustentos'), 'active' => request()->routeIs('reportes.sustentos'), 'icon' => 'clipboard'],
            ];

            $secciones[] = ['label' => 'Reportes', 'items' => $reportes];

            $personas = [];

            if ($puedeAltaUsuarios) {
                $personas[] = ['label' => 'Trabajadores', 'route' => route('usuarios.index'), 'active' => request()->routeIs('usuarios.*'), 'icon' => 'users'];
            }

            if ($esAdmin) {
                $personas[] = ['label' => 'Cuentas de usuario', 'route' => route('usuarios-admin.index'), 'active' => request()->routeIs('usuarios-admin.*'), 'icon' => 'user-circle'];
            }

            if ($personas) {
                $secciones[] = ['label' => 'Personas', 'items' => $personas];
            }

            if ($esAdmin) {
                $secciones[] = ['label' => 'Catálogos', 'items' => [
                    ['label' => 'Resumen', 'route' => route('catalogos.index'), 'active' => request()->routeIs('catalogos.*'), 'icon' => 'grid'],
                    ['label' => 'Sedes', 'route' => route('sedes.index'), 'active' => request()->routeIs('sedes.*'), 'icon' => 'map-pin'],
                    ['label' => 'Unidades orgánicas', 'route' => route('unidades-organicas.index'), 'active' => request()->routeIs('unidades-organicas.*'), 'icon' => 'building'],
                    ['label' => 'Motivos', 'route' => route('motivos.index'), 'active' => request()->routeIs('motivos.*'), 'icon' => 'tag'],
                    ['label' => 'Turnos', 'route' => route('turnos.index'), 'active' => request()->routeIs('turnos.*'), 'icon' => 'clock'],
                    ['label' => 'Feriados', 'route' => route('feriados.index'), 'active' => request()->routeIs('feriados.*'), 'icon' => 'calendar'],
                ]];

                $secciones[] = ['label' => 'Sistema', 'items' => [
                    ['label' => 'Configuraciones', 'route' => route('configuraciones.index'), 'active' => request()->routeIs('configuraciones.*'), 'icon' => 'cog'],
                    ['label' => 'Horario de RR. HH.', 'route' => route('horario-rrhh.index'), 'active' => request()->routeIs('horario-rrhh.*'), 'icon' => 'clock'],
                ]];
            }
        @endphp

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

                <main class="app-content">
                    @isset($header)
                        <div class="mb-6">{{ $header }}</div>
                    @endisset

                    @include('layouts.partials.flash')

                    {{ $slot }}
                </main>
            </div>
        </div>
    @else
        @php
            $navItems = [
                ['label' => 'Papeletas', 'route' => route('trabajador.papeletas.index'), 'active' => request()->routeIs('trabajador.papeletas.index', 'trabajador.papeletas.show'), 'icon' => 'document'],
                ['label' => 'Nueva', 'route' => route('trabajador.papeletas.create'), 'active' => request()->routeIs('trabajador.papeletas.create'), 'icon' => 'plus-circle'],
                ['label' => 'Calendario', 'route' => route('turnos.calendario.individual'), 'active' => request()->routeIs('turnos.calendario.individual'), 'icon' => 'calendar'],
                ['label' => 'Perfil', 'route' => route('profile.show'), 'active' => request()->routeIs('profile.show'), 'icon' => 'user-circle'],
            ];
        @endphp

        @include('layouts.partials.mobile-topbar')

        <main class="mobile-content">
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
