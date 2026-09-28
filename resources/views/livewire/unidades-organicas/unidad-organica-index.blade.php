<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Unidades orgánicas">
            <x-admin.boton-nuevo :href="route('unidades-organicas.crear')">Nueva unidad</x-admin.boton-nuevo>
        </x-admin.encabezado>

        <x-admin.mensajes />

        <div class="flex flex-wrap gap-4">
            <x-admin.filtro-campo label="Buscar" for="unidad-organica-index-buscar">
                <x-input id="unidad-organica-index-buscar" type="text" wire:model.live.debounce.400ms="buscar" placeholder="Nombre de la unidad" />
            </x-admin.filtro-campo>
        </div>

        <x-admin.tabla :columnas="['Nombre', 'Unidad padre', 'Tipo', 'Jefe', 'Turnos sin jefe', 'Activo', '']">
            @forelse ($unidades as $unidad)
                <tr wire:key="unidad-{{ $unidad->id }}">
                    <td class="px-4 py-3">{{ $unidad->nombre }}</td>
                    <td class="px-4 py-3">{{ $unidad->padre?->nombre ?? '— (raíz)' }}</td>
                    <td class="px-4 py-3">{{ $unidad->tipo }}</td>
                    <td class="px-4 py-3">{{ $unidad->jefe?->name }}</td>
                    <td class="px-4 py-3 text-sm">
                        @php($faltan = $unidad->turnosSinJefe())
                        @if ($faltan !== [])
                            <span class="text-ambar-700 dark:text-ambar-400">{{ collect($faltan)->map(fn ($t) => \App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::TURNOS[$t] ?? $t)->implode(', ') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$unidad->activo" /></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <x-admin.accion-editar :href="route('unidades-organicas.editar', $unidad)" />
                        <x-admin.accion-eliminar
                            wire:click="eliminar({{ $unidad->id }})"
                            wire:confirm="¿Eliminar la unidad orgánica &quot;{{ $unidad->nombre }}&quot;?"
                        />
                    </td>
                </tr>
            @empty
                <x-admin.fila-vacia :colspan="7">{{ $buscar ? 'Ninguna unidad coincide con la búsqueda.' : 'Aún no hay unidades orgánicas registradas.' }}</x-admin.fila-vacia>
            @endforelse
        </x-admin.tabla>
    </div>
</div>
