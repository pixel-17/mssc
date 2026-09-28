<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Sedes">
            <x-admin.boton-nuevo :href="route('sedes.crear')">Nueva sede</x-admin.boton-nuevo>
        </x-admin.encabezado>

        <x-admin.mensajes />

        <x-admin.tabla :columnas="['Nombre', 'Dirección', 'Radio (m)', 'Trabajadores', 'Activa', '']">
            @forelse ($sedes as $sede)
                <tr wire:key="sede-{{ $sede->id }}">
                    <td class="px-4 py-3">{{ $sede->nombre }}</td>
                    <td class="px-4 py-3">{{ $sede->direccion }}</td>
                    <td class="px-4 py-3">{{ $sede->radio_metros }}</td>
                    <td class="px-4 py-3">{{ $sede->usuarios_count }}</td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$sede->activo" etiqueta-si="Activa" etiqueta-no="Inactiva" /></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <x-admin.accion-editar :href="route('sedes.editar', $sede)" />
                        <x-admin.accion-eliminar
                            wire:click="eliminar({{ $sede->id }})"
                            wire:confirm="¿Eliminar la sede &quot;{{ $sede->nombre }}&quot;?"
                        />
                    </td>
                </tr>
            @empty
                <x-admin.fila-vacia :colspan="6">Aún no hay sedes registradas.</x-admin.fila-vacia>
            @endforelse
        </x-admin.tabla>
    </div>
</div>
