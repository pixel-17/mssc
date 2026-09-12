@php
    $puedeTrabajador = auth()->user()?->hasRole('trabajador');
    $puedeJefe = auth()->user()?->can('crearTrabajadorPropio', App\Models\User::class);
    $puedeRrhh = auth()->user()?->hasRole('rrhh');
    $puedeUsuarios = auth()->user()?->can('puedeCrearAlgo', App\Models\User::class);
@endphp

<div class="p-6 lg:p-10 bg-ocean-gradient relative overflow-hidden">
    <div class="pointer-events-none absolute -top-10 -right-10 size-56 rounded-full bg-white/10 blur-3xl animate-float-slow"></div>
    <div class="relative flex items-center gap-4">
        <span class="icon-chip !size-14 !rounded-2xl bg-white/15 border-white/25 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="size-7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>
        </span>
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">
                Sistema de Gestión de Papeletas
            </h1>
            <p class="text-white/70 text-sm font-medium">Municipalidad de Santiago de Cusco</p>
        </div>
    </div>

    <p class="relative mt-6 text-white/80 leading-relaxed max-w-2xl text-sm">
        Bienvenido(a), <span class="font-semibold text-white">{{ auth()->user()->name }}</span>.
        Desde aquí puedes gestionar tus permisos de salida y retorno, revisar tu bandeja de aprobaciones
        y hacer seguimiento del estado de tus trámites.
    </p>
</div>

<div class="bg-transparent grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 p-6 lg:p-10">
    @if($puedeTrabajador)
        <a href="{{ route('trabajador.papeletas.create') }}" class="glass-card p-5 group hover:shadow-glass-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center gap-3">
                <span class="icon-chip bg-ocean-100 text-ocean-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                </span>
                <h2 class="font-bold text-ocean-950">Nueva papeleta</h2>
            </div>
            <p class="mt-3 text-gray-600 text-sm leading-relaxed">
                Solicita un permiso de salida o retorno de forma rápida.
            </p>
            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-ocean-700 group-hover:gap-2 transition-all">
                Crear solicitud
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="size-4 fill-ocean-600"><path fill-rule="evenodd" d="M5 10a.75.75 0 01.75-.75h6.638L10.23 7.29a.75.75 0 111.04-1.08l3.5 3.25a.75.75 0 010 1.08l-3.5 3.25a.75.75 0 11-1.04-1.08l2.158-1.96H5.75A.75.75 0 015 10z" clip-rule="evenodd" /></svg>
            </span>
        </a>

        <a href="{{ route('trabajador.papeletas.index') }}" class="glass-card p-5 group hover:shadow-glass-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center gap-3">
                <span class="icon-chip bg-ocean-100 text-ocean-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" /></svg>
                </span>
                <h2 class="font-bold text-ocean-950">Mis papeletas</h2>
            </div>
            <p class="mt-3 text-gray-600 text-sm leading-relaxed">
                Revisa el historial y estado de tus solicitudes.
            </p>
            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-ocean-700 group-hover:gap-2 transition-all">
                Ver historial
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="size-4 fill-ocean-600"><path fill-rule="evenodd" d="M5 10a.75.75 0 01.75-.75h6.638L10.23 7.29a.75.75 0 111.04-1.08l3.5 3.25a.75.75 0 010 1.08l-3.5 3.25a.75.75 0 11-1.04-1.08l2.158-1.96H5.75A.75.75 0 015 10z" clip-rule="evenodd" /></svg>
            </span>
        </a>
    @endif

    @if($puedeJefe)
        <a href="{{ route('jefe.papeletas.index') }}" class="glass-card p-5 group hover:shadow-glass-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center gap-3">
                <span class="icon-chip bg-amber-100 text-amber-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
                <h2 class="font-bold text-ocean-950">Bandeja de Jefe</h2>
            </div>
            <p class="mt-3 text-gray-600 text-sm leading-relaxed">
                Aprueba, observa o rechaza las solicitudes de tu equipo.
            </p>
            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-amber-700 group-hover:gap-2 transition-all">
                Ir a bandeja
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="size-4 fill-amber-600"><path fill-rule="evenodd" d="M5 10a.75.75 0 01.75-.75h6.638L10.23 7.29a.75.75 0 111.04-1.08l3.5 3.25a.75.75 0 010 1.08l-3.5 3.25a.75.75 0 11-1.04-1.08l2.158-1.96H5.75A.75.75 0 015 10z" clip-rule="evenodd" /></svg>
            </span>
        </a>
    @endif

    @if($puedeRrhh)
        <a href="{{ route('rrhh.papeletas.index') }}" class="glass-card p-5 group hover:shadow-glass-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center gap-3">
                <span class="icon-chip bg-emerald-100 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                </span>
                <h2 class="font-bold text-ocean-950">Papeletas RRHH</h2>
            </div>
            <p class="mt-3 text-gray-600 text-sm leading-relaxed">
                Supervisa y valida las papeletas a nivel institucional.
            </p>
            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-emerald-700 group-hover:gap-2 transition-all">
                Ir a RRHH
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="size-4 fill-emerald-600"><path fill-rule="evenodd" d="M5 10a.75.75 0 01.75-.75h6.638L10.23 7.29a.75.75 0 111.04-1.08l3.5 3.25a.75.75 0 010 1.08l-3.5 3.25a.75.75 0 11-1.04-1.08l2.158-1.96H5.75A.75.75 0 015 10z" clip-rule="evenodd" /></svg>
            </span>
        </a>
    @endif

    @if($puedeUsuarios)
        <a href="{{ route('usuarios.index') }}" class="glass-card p-5 group hover:shadow-glass-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center gap-3">
                <span class="icon-chip bg-violet-100 text-violet-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                </span>
                <h2 class="font-bold text-ocean-950">Usuarios</h2>
            </div>
            <p class="mt-3 text-gray-600 text-sm leading-relaxed">
                Administra cuentas, vínculos y trabajadores del sistema.
            </p>
            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-violet-700 group-hover:gap-2 transition-all">
                Administrar
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="size-4 fill-violet-600"><path fill-rule="evenodd" d="M5 10a.75.75 0 01.75-.75h6.638L10.23 7.29a.75.75 0 111.04-1.08l3.5 3.25a.75.75 0 010 1.08l-3.5 3.25a.75.75 0 11-1.04-1.08l2.158-1.96H5.75A.75.75 0 015 10z" clip-rule="evenodd" /></svg>
            </span>
        </a>
    @endif

    <a href="{{ route('profile.show') }}" class="glass-card p-5 group hover:shadow-glass-lg hover:-translate-y-0.5 transition-all duration-200">
        <div class="flex items-center gap-3">
            <span class="icon-chip bg-ocean-950/10 text-ocean-900">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </span>
            <h2 class="font-bold text-ocean-950">Mi perfil</h2>
        </div>
        <p class="mt-3 text-gray-600 text-sm leading-relaxed">
            Actualiza tus datos personales y de seguridad.
        </p>
        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-ocean-900 group-hover:gap-2 transition-all">
            Ver perfil
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="size-4 fill-ocean-900"><path fill-rule="evenodd" d="M5 10a.75.75 0 01.75-.75h6.638L10.23 7.29a.75.75 0 111.04-1.08l3.5 3.25a.75.75 0 010 1.08l-3.5 3.25a.75.75 0 11-1.04-1.08l2.158-1.96H5.75A.75.75 0 015 10z" clip-rule="evenodd" /></svg>
        </span>
    </a>
</div>
