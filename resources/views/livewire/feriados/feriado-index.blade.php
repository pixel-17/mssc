<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Feriados">
            <x-admin.boton-nuevo :href="route('feriados.crear')">Nuevo feriado</x-admin.boton-nuevo>
        </x-admin.encabezado>

        <x-admin.mensajes />

        <x-admin.tabla :columnas="['Fecha', 'Descripción', '']">
            @forelse ($feriados as $feriado)
                <tr wire:key="feriado-{{ $feriado->id }}">
                    <td class="px-4 py-3">{{ $feriado->fecha?->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">{{ $feriado->descripcion }}</td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <x-admin.accion-editar :href="route('feriados.editar', $feriado)" />
                        <x-admin.accion-eliminar
                            wire:click="eliminar({{ $feriado->id }})"
                            wire:confirm="¿Eliminar el feriado &quot;{{ $feriado->descripcion }}&quot; ({{ $feriado->fecha?->format('d/m/Y') }})?"
                        />
                    </td>
                </tr>
            @empty
                <x-admin.fila-vacia :colspan="3">Aún no hay feriados registrados.</x-admin.fila-vacia>
            @endforelse
        </x-admin.tabla>
    </div>
</div>
