<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            Configurar turno — {{ $trabajador->name }} {{ $trabajador->apellido }}
        </h2>

        <p class="text-sm text-gray-500">
            Régimen {{ $trabajador->regimen }}. El trabajador trabajará {{ $diasTrabajo }} días seguidos en el
            turno elegido y descansará {{ $diasDescanso }}, repitiendo el ciclo sin cortarse entre meses. Si no
            vuelves a cargar una actualización, el mes siguiente se genera solo con esta misma configuración.
        </p>

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Turno</label>
                    <select wire:model="turno" class="w-full rounded-md border-gray-300 dark:bg-gray-800" @if(count($opcionesTurno) === 1) disabled @endif>
                        @foreach ($opcionesTurno as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    @error('turno') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Fecha en que empieza su próximo bloque de trabajo</label>
                    <input type="date" wire:model="fechaAncla" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('fechaAncla') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Días de trabajo seguidos</label>
                    <input type="number" min="1" max="30" wire:model="diasTrabajo" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('diasTrabajo') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Días de descanso</label>
                    <input type="number" min="1" max="30" wire:model="diasDescanso" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('diasDescanso') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('turnos.index') }}" class="text-sm text-gray-500">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                    Guardar y generar este mes
                </button>
            </div>
        </form>
    </div>
</div>
