<div>
    <div class="max-w-7xl mx-auto pt-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Programar horarios" />

        <x-admin.mensajes />

        <p class="text-sm text-gray-500">
            Busca a la persona. Si es trabajador, programas su horario; si es jefe, se abre la grilla de su equipo
            y lo programas como lo haría él. Lo que guardes queda registrado a tu nombre.
        </p>

        <x-admin.filtro-campo label="Buscar usuario" for="programacion-admin-buscar">
            <x-input id="programacion-admin-buscar" type="text" wire:model.live.debounce.400ms="buscar" placeholder="Nombre, apellido, DNI o correo" autocomplete="off" />
        </x-admin.filtro-campo>

        @if (mb_strlen(trim($buscar)) >= 2)
            <x-admin.tabla :columnas="['Nombre', 'DNI', 'Tipo', 'Régimen', 'Unidad', '']">
                @forelse ($resultados as $usuario)
                    @php
                        $esJefeDeArea = in_array($usuario->id, $idsJefesDeArea, true);
                        $esJefe = in_array($usuario->id, $idsJefes, true);
                    @endphp
                    <tr wire:key="programacion-admin-{{ $usuario->id }}">
                        <td class="px-4 py-3">
                            {{ $usuario->nombre_completo }}
                            @unless ($usuario->activo)
                                <span class="ml-1 text-xs text-gray-400">(inactivo)</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3">{{ $usuario->dni }}</td>
                        <td class="px-4 py-3">
                            @if ($esJefeDeArea)
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
                            @if ($esJefe)
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
                                    Crear/editar horario
                                </a>
                            @endif
                            <a href="{{ route('turnos.calendario.individual-de', $usuario) }}" class="btn-row">
                                <x-icon name="calendar" class="size-3.5" />
                                Calendario
                            </a>
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
        <livewire:turnos.calendario-equipo-index :jefe-id="$jefe->id" :key="'equipo-admin-'.$jefe->id" />
    @endif
</div>
