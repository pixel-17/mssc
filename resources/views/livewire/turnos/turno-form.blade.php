<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            {{ $turno ? 'Editar turno' : 'Nuevo turno' }}
        </h2>

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Trabajador</label>
                    <select wire:model="userId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— Selecciona —</option>
                        @foreach ($trabajadores as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('userId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Sede</label>
                    <select wire:model="sedeId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— (ninguna) —</option>
                        @foreach ($sedes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('sedeId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Fecha</label>
                    <input type="date" wire:model="fecha" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('fecha') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <label class="flex items-center gap-2 mt-6">
                    <input type="checkbox" wire:model.live="esDescanso" class="rounded">
                    <span class="text-sm">Día de descanso</span>
                </label>

                @unless ($esDescanso)
                    <div>
                        <label class="block text-sm font-medium mb-1">Hora inicio</label>
                        <input type="time" wire:model="horaInicio" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        @error('horaInicio') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Hora fin</label>
                        <input type="time" wire:model="horaFin" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        @error('horaFin') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                @endunless
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('turnos.index') }}" class="text-sm text-gray-500">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
