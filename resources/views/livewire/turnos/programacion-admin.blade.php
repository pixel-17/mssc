<div>
    <div class="max-w-7xl mx-auto pt-10 sm:px-6 lg:px-8 space-y-4">
        {{-- Título + descripción en bloque compacto --}}
        <div class="space-y-1">
            <x-admin.encabezado titulo="Programar horarios" />
            <p class="text-sm text-gray-500">
                Busca a la persona. Si es trabajador, programas su horario; si es jefe, se abre la grilla de su equipo
                y lo programas como lo haría él. Lo que guardes queda registrado a tu nombre.
            </p>
        </div>

        <x-admin.mensajes />

        {{-- Buscar usuario pegado al título --}}
        <div class="flex items-end gap-3">
            <div class="flex-1 max-w-sm">
                <label for="programacion-admin-buscar" class="block text-sm font-medium mb-1">Buscar usuario</label>
                <x-input id="programacion-admin-buscar" type="text" wire:model.live.debounce.400ms="buscar" placeholder="Nombre, apellido, DNI o correo" autocomplete="off" class="w-full" />
            </div>
        </div>

        @if (mb_strlen(trim($buscar)) >= 2)
            <x-admin.tabla :columnas="['Nombre', 'DNI', 'Tipo', 'Régimen', 'Unidad', '']">
                @forelse ($resultados as $usuario)
                    @php
                        $esJefeDeArea = in_array($usuario->id, $idsJefesDeArea, true);
                        $esJefe = in_array($usuario->id, $idsJefes, true);
                        $esAdmin = $usuario->hasRole('admin');
                        $esRrhh = $usuario->hasRole('rrhh');
                    @endphp
                    <tr wire:key="programacion-admin-{{ $usuario->id }}">
                        <td class="px-4 py-3">
                            {{ $usuario->nombre_completo }}
                            @unless ($usuario->activo)
                                <span class="ml-1 text-xs text-gray-500">(inactivo)</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3">{{ $usuario->dni }}</td>
                        <td class="px-4 py-3">
                            @if ($esAdmin)
                                <span class="inline-flex items-center rounded bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">Administrador</span>
                            @elseif ($esRrhh)
                                <span class="inline-flex items-center rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">RRHH</span>
                            @elseif ($esJefeDeArea)
                                <span class="inline-flex items-center rounded bg-tinta-100 px-2 py-0.5 text-xs font-semibold text-tinta-800">Jefe de área</span>
                            @elseif ($esJefe)
                                <span class="inline-flex items-center rounded bg-tinta-100 px-2 py-0.5 text-xs font-semibold text-tinta-800">Jefe inmediato</span>
                            @else
                                <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">Trabajador</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $usuario->regimen }}</td>
                        <td class="px-4 py-3">{{ $usuario->unidadOrganica?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            @if ($esAdmin)
                                <span class="text-xs text-gray-400">—</span>
                            @elseif ($esJefe)
                                <button type="button" wire:click="seleccionarJefe({{ $usuario->id }})" class="btn-row">
                                    <x-icon name="users" class="size-3.5" />
                                    Programar su equipo
                                </button>
                            @elseif ($usuario->regimen === '728')
                                <a href="{{ route('turnos.programacion', $usuario) }}" class="btn-row">
                                    <x-icon name="clock" class="size-3.5" />
                                    Programar
                                </a>
                            @else
                                <a href="{{ route('turnos.configuracion', $usuario) }}" class="btn-row">
                                    <x-icon name="clock" class="size-3.5" />
                                    Cargar horario ordinario
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-admin.fila-vacia :colspan="6">No se encontró a nadie con esa búsqueda.</x-admin.fila-vacia>
                @endforelse
            </x-admin.tabla>

            @if ($hayMas)
                <p class="text-xs text-gray-500">Se muestran los primeros {{ $resultados->count() }}. Escribe más del nombre o el DNI para acotar.</p>
            @endif
        @endif

        @if ($jefe)
            <div class="flex items-center justify-between flex-wrap gap-2 rounded-lg border border-tinta-200 bg-tinta-50 px-4 py-3">
                <p class="text-sm text-tinta-900">
                    Programando el equipo de <span class="font-semibold">{{ $jefe->nombre_completo }}</span>
                </p>
                <button type="button" wire:click="quitarJefe" class="text-sm text-gray-600 underline hover:no-underline">Cerrar</button>
            </div>
        @endif
    </div>

    @if ($jefe)
        <div id="programacion-admin-calendario" x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
            <livewire:turnos.calendario-equipo-index :jefe-id="$jefe->id" :key="'equipo-admin-'.$jefe->id" />
        </div>
    @endif
</div>