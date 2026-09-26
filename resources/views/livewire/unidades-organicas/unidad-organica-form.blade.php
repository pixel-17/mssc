<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
            {{ $unidad ? 'Editar unidad orgánica' : 'Nueva unidad orgánica' }}
        </h2>

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div>
                <label for="unidad-organica-form-nombre" class="block text-sm font-medium mb-1">Nombre</label>
                <input id="unidad-organica-form-nombre" type="text" wire:model="nombre" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                @error('nombre') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="unidad-organica-form-tipo" class="block text-sm font-medium mb-1">Tipo</label>
                <select id="unidad-organica-form-tipo" wire:model="tipo" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    <option value="">— (sin tipo) —</option>
                    @foreach (\App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::TIPOS as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Solo pinta el organigrama, no afecta el escalamiento de papeletas.</p>
                @error('tipo') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="unidad-organica-form-parentId" class="block text-sm font-medium mb-1">Unidad padre</label>
                <select id="unidad-organica-form-parentId" wire:model="parentId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    <option value="">— (raíz) —</option>
                    @foreach ($padresDisponibles as $id => $nombrePadre)
                        <option value="{{ $id }}">{{ $nombrePadre }}</option>
                    @endforeach
                </select>
                @error('parentId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="unidad-organica-form-jefeId" class="block text-sm font-medium mb-1">Jefe de la unidad</label>
                <select id="unidad-organica-form-jefeId" wire:model="jefeId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    <option value="">— (ninguno) —</option>
                    @foreach ($jefesDisponibles as $id => $nombreJefe)
                        <option value="{{ $id }}">{{ $nombreJefe }}</option>
                    @endforeach
                </select>
                @error('jefeId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="activo" class="rounded">
                <span class="text-sm">Activo</span>
            </label>

            @if ($unidad)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium">Jefes inmediatos adicionales (régimen 728)</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Opcional, uno o varios. No se elige un turno fijo aquí: el turno que cada uno
                                cubre sale de su propia programación de calendario — configúrasela con
                                "Configurar turno" tras guardar. Con varios coincidiendo en turno, cualquiera
                                puede decidir una papeleta — el que actúe primero. Si nadie coincide con el
                                turno vigente de un trabajador, su Jefe Inmediato sigue siendo
                                "{{ $jefesDisponibles[$jefeId] ?? 'el jefe de la unidad' }}" (arriba) solo para
                                régimen 276; en 728 la papeleta se bloquea hasta que alguien coincida.
                            </p>
                        </div>
                        <button type="button" wire:click="agregarJefeAdicional" class="text-xs text-tinta-700 hover:underline whitespace-nowrap">
                            + Agregar jefe
                        </button>
                    </div>

                    @forelse ($jefesAdicionales as $indice => $jefeIdAdicional)
                        <div class="flex items-center gap-2" wire:key="jefe-adicional-{{ $indice }}">
                            <select wire:model="jefesAdicionales.{{ $indice }}" class="flex-1 rounded-md border-gray-300 dark:bg-gray-800">
                                <option value="">— (sin elegir) —</option>
                                @foreach ($jefesDisponibles as $id => $nombreJefe)
                                    <option value="{{ $id }}">{{ $nombreJefe }}</option>
                                @endforeach
                            </select>

                            @if ($jefeIdAdicional)
                                <a href="{{ route('turnos.configuracion', $jefeIdAdicional) }}" target="_blank" class="text-xs text-gray-500 hover:underline whitespace-nowrap">
                                    Configurar turno
                                </a>
                            @endif

                            <button type="button" wire:click="quitarJefeAdicional({{ $indice }})" class="text-xs text-red-600 hover:underline whitespace-nowrap">
                                Quitar
                            </button>
                        </div>
                        @error("jefesAdicionales.{$indice}") <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    @empty
                        <p class="text-xs text-gray-400">Sin jefes inmediatos adicionales asignados.</p>
                    @endforelse
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('unidades-organicas.index') }}" class="text-sm text-gray-500">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-tinta-800 text-white rounded-md text-sm">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
