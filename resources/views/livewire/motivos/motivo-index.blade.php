<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Motivos">
            <x-admin.boton-nuevo :href="route('motivos.crear')">Nuevo motivo</x-admin.boton-nuevo>
        </x-admin.encabezado>

        <x-admin.mensajes />

        <div class="flex flex-wrap gap-4">
            <x-admin.filtro-campo label="Buscar" for="motivo-index-buscar">
                <x-input id="motivo-index-buscar" type="text" wire:model.live.debounce.400ms="buscar" placeholder="Código o nombre" />
            </x-admin.filtro-campo>
        </div>

        <x-admin.tabla :columnas="['Código', 'Nombre', 'Requiere justificación', 'Aplica descuento', 'Activo', '']">
            @forelse ($motivos as $motivo)
                <tr wire:key="motivo-{{ $motivo->id }}">
                    <td class="px-4 py-3">{{ $motivo->codigo }}</td>
                    <td class="px-4 py-3">{{ $motivo->nombre }}</td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$motivo->requiere_sustento_en_retorno" etiqueta-si="Sí" etiqueta-no="No" /></td>
                    <td class="px-4 py-3">{{ match ($motivo->consecuenciaAlTerminar()) { 'descuenta' => 'Sí', 'justificar' => 'Solo si no la presenta', default => 'No' } }}</td>
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
                <x-admin.fila-vacia :colspan="6">{{ $buscar ? 'Ningún motivo coincide con la búsqueda.' : 'Aún no hay motivos registrados.' }}</x-admin.fila-vacia>
            @endforelse
        </x-admin.tabla>
    </div>
</div>
