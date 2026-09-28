<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Motivos">
            <x-admin.boton-nuevo :href="route('motivos.crear')">Nuevo motivo</x-admin.boton-nuevo>
        </x-admin.encabezado>

        <x-admin.mensajes />

        <x-admin.tabla :columnas="['Código', 'Nombre', 'Adjunto', 'Sustento', 'Cierre s/retorno', 'Activo', '']">
            @forelse ($motivos as $motivo)
                <tr wire:key="motivo-{{ $motivo->id }}">
                    <td class="px-4 py-3">{{ $motivo->codigo }}</td>
                    <td class="px-4 py-3">{{ $motivo->nombre }}</td>
                    <td class="px-4 py-3">{{ $motivo->adjunto }}</td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$motivo->requiere_sustento_en_retorno" etiqueta-si="Sí" etiqueta-no="No" /></td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$motivo->permite_cierre_sin_retorno" etiqueta-si="Sí" etiqueta-no="No" /></td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$motivo->activo" /></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <x-admin.accion-editar :href="route('motivos.editar', $motivo)" />
                        <x-admin.accion-eliminar
                            wire:click="eliminar({{ $motivo->id }})"
                            wire:confirm="¿Eliminar el motivo &quot;{{ $motivo->nombre }}&quot;?"
                        />
                    </td>
                </tr>
            @empty
                <x-admin.fila-vacia :colspan="7">Aún no hay motivos registrados.</x-admin.fila-vacia>
            @endforelse
        </x-admin.tabla>
    </div>
</div>
