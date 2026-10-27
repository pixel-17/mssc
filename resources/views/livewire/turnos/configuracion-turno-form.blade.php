<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
            Configurar turno — {{ $trabajador->name }} {{ $trabajador->apellido }}
        </h2>

        @if ($trabajador->regimen === '276')
            {{-- 276: el admin ya configuró los días laborables globalmente.
                 Aquí solo se muestra qué se va a pintar y se confirma con un botón. --}}
            <p class="text-sm text-gray-500">
                Régimen 276 (horario ordinario). Los días de trabajo los define el Admin en Configuraciones
                y aplican igual para todos. Aquí solo se genera el horario de este mes para este trabajador.
            </p>

            <div class="glass-card p-6 space-y-4">
                <div>
                    <p class="text-sm font-medium mb-2">Días laborables configurados por el Admin</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'] as $iso => $nombre)
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium
                                {{ in_array($nombre, $diasLaborables276 ?? []) ? 'bg-tinta-50 text-tinta-800 border-tinta-300 dark:bg-tinta-500/10 dark:text-tinta-300 dark:border-tinta-500/30' : 'bg-gray-50 text-gray-400 border-gray-200 dark:bg-gray-900 dark:border-gray-800' }}">
                                {{ $nombre }}
                            </span>
                        @endforeach
                    </div>
                </div>

                @if ($previsualizacion)
                    <div class="border-t pt-4 dark:border-gray-700">
                        <p class="text-sm font-medium mb-2">Vista previa (próximas 2 semanas)</p>
                        <div class="grid grid-cols-7 gap-1.5 text-center">
                            @foreach ($previsualizacion as $dia)
                                <div wire:key="configuracion-turno-form-preview-{{ $dia['fecha'] }}"
                                    class="rounded-md border px-1 py-2 text-xs
                                    {{ $dia['descanso'] ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' : 'bg-tinta-50 text-tinta-800 border-tinta-200 dark:bg-tinta-500/10 dark:text-tinta-300 dark:border-tinta-500/30' }}">
                                    <div class="font-medium">{{ $dia['etiqueta'] }}</div>
                                    <div class="mt-1">{{ $dia['descanso'] ? 'D' : 'T' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 border-t pt-4 dark:border-gray-700">
                    <a href="{{ route('turnos.index') }}" class="text-sm text-gray-500">Cancelar</a>
                    <button type="button" wire:click="guardar" wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-tinta-800 text-white rounded-md text-sm disabled:opacity-50">
                        <span wire:loading wire:target="guardar">Generando…</span>
                        <span wire:loading.remove wire:target="guardar">Generar horario de este mes</span>
                    </button>
                </div>
            </div>

        @else
            {{-- 728: formulario completo con ciclo editable --}}
            <p class="text-sm text-gray-500">
                Régimen {{ $trabajador->regimen }}. El trabajador trabajará {{ $diasTrabajo }} días seguidos en el
                turno elegido y descansará {{ $diasDescanso }}, repitiendo el ciclo sin cortarse entre meses.
            </p>

            <form wire:submit="guardar" class="glass-card p-6 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="configuracion-turno-form-turno" class="block text-sm font-medium mb-1">Turno</label>
                        <select id="configuracion-turno-form-turno" wire:model="turno" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                            @foreach ($opcionesTurno as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>
                        @error('turno') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="configuracion-turno-form-fechaAncla" class="block text-sm font-medium mb-1">Fecha en que empieza su próximo bloque de trabajo</label>
                        <input id="configuracion-turno-form-fechaAncla" type="date" wire:model.live="fechaAncla" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        @error('fechaAncla') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="configuracion-turno-form-diasTrabajo" class="block text-sm font-medium mb-1">Días de trabajo seguidos</label>
                        <input id="configuracion-turno-form-diasTrabajo" type="number" min="1" max="30" wire:model.live="diasTrabajo" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        @error('diasTrabajo') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="configuracion-turno-form-diasDescanso" class="block text-sm font-medium mb-1">Días de descanso</label>
                        <input id="configuracion-turno-form-diasDescanso" type="number" min="1" max="30" wire:model.live="diasDescanso" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        @error('diasDescanso') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="border-t pt-4 dark:border-gray-700">
                    <label class="block text-sm font-medium mb-2">O marca directamente qué días descansa</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $i => $nombreDia)
                            @php($valorDia = $i + 1)
                            <label class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm cursor-pointer select-none
                                {{ in_array($valorDia, $diasSemanaDescanso) ? 'bg-tinta-800 text-white border-tinta-800' : 'bg-white dark:bg-gray-800 dark:border-gray-700' }}">
                                <input type="checkbox" wire:model="diasSemanaDescanso" value="{{ $valorDia }}" class="hidden">
                                {{ $nombreDia }}
                            </label>
                        @endforeach
                        <button type="button" wire:click="aplicarDiasSemana" class="inline-flex items-center px-3 py-1.5 rounded-md border text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">
                            Aplicar
                        </button>
                    </div>
                    @error('diasSemanaDescanso') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
                </div>

                @if ($previsualizacion)
                    <div class="border-t pt-4 dark:border-gray-700">
                        <p class="text-sm font-medium mb-2">Vista previa (próximas 2 semanas)</p>
                        <div class="grid grid-cols-7 gap-1.5 text-center">
                            @foreach ($previsualizacion as $dia)
                                <div wire:key="configuracion-turno-form-preview-{{ $dia['fecha'] }}"
                                    class="rounded-md border px-1 py-2 text-xs
                                    {{ $dia['descanso'] ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' : 'bg-tinta-50 text-tinta-800 border-tinta-200 dark:bg-tinta-500/10 dark:text-tinta-300 dark:border-tinta-500/30' }}">
                                    <div class="font-medium">{{ $dia['etiqueta'] }}</div>
                                    <div class="mt-1">{{ $dia['descanso'] ? 'Descanso' : 'Trabajo' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('turnos.index') }}" class="text-sm text-gray-500">Cancelar</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-tinta-800 text-white rounded-md text-sm">
                        Guardar y generar este mes
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>