<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Turnos">
            <x-admin.boton-nuevo :href="route('turnos.crear')">Nuevo turno</x-admin.boton-nuevo>
        </x-admin.encabezado>

        <x-admin.mensajes />

        <div class="flex flex-wrap gap-4">
            <x-admin.filtro-campo label="Filtrar por trabajador" for="turno-index-userId">
                <x-select id="turno-index-userId" wire:model.live="userId">
                    <option value="">Todos</option>
                    @foreach ($trabajadores as $id => $nombre)
                        <option value="{{ $id }}">{{ $nombre }}</option>
                    @endforeach
                </x-select>
            </x-admin.filtro-campo>
        </div>

        <x-admin.tabla :columnas="['Trabajador', 'Sede', 'Fecha', 'Inicio', 'Fin', 'Descanso', '']">
            @forelse ($turnos as $turno)
                <tr wire:key="turno-{{ $turno->id }}">
                    <td class="px-4 py-3">{{ $turno->usuario?->name }}</td>
                    <td class="px-4 py-3">{{ $turno->sede?->nombre }}</td>
                    <td class="px-4 py-3">{{ $turno->fecha?->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">{{ $turno->hora_inicio }}</td>
                    <td class="px-4 py-3">{{ $turno->hora_fin }}</td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$turno->es_descanso" etiqueta-si="Sí" etiqueta-no="No" /></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <x-admin.accion-editar :href="route('turnos.editar', $turno)" />
                        <x-admin.accion-eliminar
                            wire:click="eliminar({{ $turno->id }})"
                            wire:confirm="¿Eliminar el turno de {{ $turno->usuario?->name }} del {{ $turno->fecha?->format('d/m/Y') }}?"
                        />
                    </td>
                </tr>
            @empty
                <x-admin.fila-vacia :colspan="7">Aún no hay turnos registrados.</x-admin.fila-vacia>
            @endforelse
        </x-admin.tabla>

        {{ $turnos->links() }}
    </div>
</div>
