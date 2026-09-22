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
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                    <div>
                        <h3 class="text-sm font-medium">Jefes por turno (régimen 728)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Opcional. Si un turno queda sin asignar, el Jefe Inmediato de ese turno sigue siendo
                            "{{ $jefesDisponibles[$jefeId] ?? 'el jefe de la unidad' }}" (arriba). Solo aplica a
                            trabajadores de régimen 728 cargados en ese turno.
                        </p>
                    </div>

                    @foreach (\App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::TURNOS as $codigo => $etiqueta)
                        <div>
                            <label for="unidad-organica-form-jefe-turno-{{ $codigo }}" class="block text-sm font-medium mb-1">{{ $etiqueta }}</label>
                            <select id="unidad-organica-form-jefe-turno-{{ $codigo }}" wire:model="jefesPorTurno.{{ $codigo }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                                <option value="">— (usar jefe de la unidad) —</option>
                                @foreach ($jefesDisponibles as $id => $nombreJefe)
                                    <option value="{{ $id }}">{{ $nombreJefe }}</option>
                                @endforeach
                            </select>
                            @error("jefesPorTurno.{$codigo}") <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
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
