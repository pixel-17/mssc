{{--
    Mensajes flash del catálogo de administración. Los 8 módulos (Sedes,
    Motivos, Turnos, Unidades orgánicas, Configuraciones,
    Usuarios) siempre flashean con las mismas dos claves: session('mensaje')
    para éxito y session('error') para el aviso de "no se puede" (ver
    UsuarioAdminIndex::desactivar). Antes cada vista copiaba su propio
    bloque @if — la mayoría solo cubría 'mensaje' y se olvidaba de
    'error' (UnidadesOrganicas y Usuarios eran la excepción). Un solo
    componente asegura que las 8 pantallas muestren ambos siempre.

    No reemplaza a <x-flash-messages> (session('success'), usada fuera del
    catálogo admin) para no tocar esas ~20 vistas con otra convención de
    nombre de clave.
--}}

@if (session('mensaje'))
    <div class="glass-card p-4 flex items-start gap-3 text-sm text-registro-700 dark:text-registro-500" role="status">
        <x-icon name="shield" class="size-5 shrink-0 mt-0.5" />
        <span>{{ session('mensaje') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="glass-card p-4 flex items-start gap-3 text-sm text-alarma-700 dark:text-alarma-500" role="alert">
        <x-icon name="shield" class="size-5 shrink-0 mt-0.5" />
        <span>{{ session('error') }}</span>
    </div>
@endif
