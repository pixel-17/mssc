<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Usuarios">
            <x-admin.boton-nuevo :href="route('usuarios-admin.crear')">Nuevo usuario</x-admin.boton-nuevo>
        </x-admin.encabezado>

        <x-admin.mensajes />

        <div class="flex flex-wrap gap-4">
            <x-admin.filtro-campo label="Buscar" for="usuario-admin-index-buscar">
                <x-input id="usuario-admin-index-buscar" type="text" wire:model.live.debounce.400ms="buscar" placeholder="Nombre, apellido, DNI o correo" />
            </x-admin.filtro-campo>

            <x-admin.filtro-campo label="Régimen" for="usuario-admin-index-regimen">
                <x-select id="usuario-admin-index-regimen" wire:model.live="regimen">
                    <option value="">Todos</option>
                    <option value="276">276 (día)</option>
                    <option value="728">728 (rotativo)</option>
                </x-select>
            </x-admin.filtro-campo>

            <x-admin.filtro-campo label="Rol" for="usuario-admin-index-rolId">
                <x-select id="usuario-admin-index-rolId" wire:model.live="rolId">
                    <option value="">Todos</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id }}">{{ $rol->name }}</option>
                    @endforeach
                </x-select>
            </x-admin.filtro-campo>
        </div>

        <x-admin.tabla :columnas="['Nombre', 'DNI', 'Correo', 'Régimen', 'Unidad', 'Rol(es)', 'Estado', '']">
            @forelse ($usuarios as $usuario)
                <tr wire:key="usuario-{{ $usuario->id }}">
                    <td class="px-4 py-3">{{ $usuario->name }} {{ $usuario->apellido }}</td>
                    <td class="px-4 py-3">{{ $usuario->dni }}</td>
                    <td class="px-4 py-3">{{ $usuario->email }}</td>
                    <td class="px-4 py-3">{{ $usuario->regimen }}</td>
                    <td class="px-4 py-3">{{ $usuario->unidadOrganica?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $usuario->roles->pluck('name')->join(', ') }}</td>
                    <td class="px-4 py-3"><x-admin.estado :activo="$usuario->activo" /></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a href="{{ route('turnos.calendario.individual-de', $usuario) }}" class="btn-row">
                            <x-icon name="calendar" class="size-3.5" />
                            Calendario
                        </a>
                        <x-admin.accion-editar :href="route('usuarios-admin.editar', $usuario)" />
                        @if ($usuario->activo)
                            <x-admin.accion-eliminar
                                wire:click="desactivar({{ $usuario->id }})"
                                wire:confirm="¿Desactivar a {{ $usuario->name }} {{ $usuario->apellido }} (DNI {{ $usuario->dni }})? No podrá iniciar sesión hasta que lo reactives."
                            >
                                Desactivar
                            </x-admin.accion-eliminar>
                        @else
                            <button
                                type="button"
                                wire:click="reactivar({{ $usuario->id }})"
                                wire:confirm="¿Reactivar a {{ $usuario->name }} {{ $usuario->apellido }} (DNI {{ $usuario->dni }})?"
                                class="btn-row"
                            >
                                <x-icon name="shield" class="size-3.5" />
                                Reactivar
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <x-admin.fila-vacia :colspan="8">No se encontraron usuarios.</x-admin.fila-vacia>
            @endforelse
        </x-admin.tabla>

        {{ $usuarios->links() }}
    </div>
</div>
