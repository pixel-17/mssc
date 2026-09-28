@props(['titulo'])

{{--
    Encabezado de las 8 pantallas "índice" del catálogo de administración
    (Sedes, Motivos, Turnos, Feriados, Unidades orgánicas, Configuraciones,
    Usuarios y el hub /catalogos). Antes cada vista repetía el mismo <h2> y
    el mismo <a> con Tailwind suelto (bg-tinta-800 rounded-md...) en vez de
    usar <x-button>/btn-primary — con el tiempo cada módulo fue quedando
    ligeramente distinto. El $slot es la acción principal ("+ Nuevo X"),
    opcional: Configuraciones no tiene alta, así que no la pasa.
--}}

<div class="flex items-center justify-between gap-4">
    <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
        {{ $titulo }}
    </h2>

    @if ($slot->isNotEmpty())
        <div class="flex items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
