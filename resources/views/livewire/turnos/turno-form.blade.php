<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$turno ? 'Editar turno' : 'Nuevo turno'" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-admin.campo label="Trabajador" for="turno-form-userId">
                    <x-select id="turno-form-userId" wire:model="userId">
                        <option value="">— Selecciona —</option>
                        @foreach ($trabajadores as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </x-select>
                </x-admin.campo>

                <x-admin.campo label="Sede" for="turno-form-sedeId">
                    <x-select id="turno-form-sedeId" wire:model="sedeId">
                        <option value="">— (ninguna) —</option>
                        @foreach ($sedes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </x-select>
                </x-admin.campo>

                <x-admin.campo label="Fecha" for="turno-form-fecha">
                    <x-input id="turno-form-fecha" type="date" wire:model="fecha" class="w-full" />
                </x-admin.campo>

                <div class="sm:mt-6">
                    <x-admin.campo-checkbox for="esDescanso" wire:model.live="esDescanso" label="Día de descanso" />
                </div>

                @unless ($esDescanso)
                    <x-admin.campo label="Hora inicio" for="turno-form-horaInicio">
                        <x-input id="turno-form-horaInicio" type="time" wire:model="horaInicio" class="w-full" />
                    </x-admin.campo>

                    <x-admin.campo label="Hora fin" for="turno-form-horaFin">
                        <x-input id="turno-form-horaFin" type="time" wire:model="horaFin" class="w-full" />
                    </x-admin.campo>
                @endunless
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('turnos.index') }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </div>
        </form>
    </div>
</div>
