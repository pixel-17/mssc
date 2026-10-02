<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$unidad ? 'Editar unidad orgánica' : 'Nueva unidad orgánica'" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <x-admin.campo label="Nombre" for="unidad-organica-form-nombre">
                <x-input id="unidad-organica-form-nombre" type="text" wire:model="nombre" class="w-full" />
            </x-admin.campo>

            <x-admin.campo label="Tipo" for="unidad-organica-form-tipo" ayuda="Solo pinta el organigrama, no afecta el escalamiento de papeletas.">
                <x-select id="unidad-organica-form-tipo" wire:model="tipo">
                    <option value="">— (sin tipo) —</option>
                    @foreach (\App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::TIPOS as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-select>
            </x-admin.campo>

            <x-admin.campo label="Unidad padre" for="unidad-organica-form-parentId">
                <x-select id="unidad-organica-form-parentId" wire:model="parentId">
                    <option value="">— (raíz) —</option>
                    @foreach ($padresDisponibles as $id => $nombrePadre)
                        <option value="{{ $id }}">{{ $nombrePadre }}</option>
                    @endforeach
                </x-select>
            </x-admin.campo>

            <x-admin.campo label="Jefe de la unidad" for="unidad-organica-form-jefeId">
                <x-select id="unidad-organica-form-jefeId" wire:model="jefeId">
                    <option value="">— (ninguno) —</option>
                    @foreach ($jefesDisponibles as $id => $nombreJefe)
                        <option value="{{ $id }}">{{ $nombreJefe }}</option>
                    @endforeach
                </x-select>
            </x-admin.campo>

            <x-admin.campo-checkbox for="activo" wire:model="activo" label="Activo" />

            @if ($unidad)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium">Otros jefes inmediatos de la unidad (régimen 728)</h3>
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
                        <button type="button" wire:click="agregarJefeAdicional" class="text-xs text-tinta-700 hover:underline whitespace-nowrap inline-flex items-center gap-1">
                            <x-icon name="plus-circle" class="size-3.5" />
                            Agregar jefe
                        </button>
                    </div>

                    @forelse ($jefesAdicionales as $indice => $jefeIdAdicional)
                        <div class="flex items-center gap-2" wire:key="jefe-adicional-{{ $indice }}">
                            <x-select wire:model="jefesAdicionales.{{ $indice }}" class="flex-1">
                                <option value="">— (sin elegir) —</option>
                                @foreach ($jefesDisponibles as $id => $nombreJefe)
                                    <option value="{{ $id }}">{{ $nombreJefe }}</option>
                                @endforeach
                            </x-select>

                            @if ($jefeIdAdicional)
                                <a href="{{ route('turnos.configuracion', $jefeIdAdicional) }}" target="_blank" class="text-xs text-gray-500 hover:underline whitespace-nowrap">
                                    Configurar turno
                                </a>
                            @endif

                            <button type="button" wire:click="quitarJefeAdicional({{ $indice }})" class="text-xs text-alarma-700 dark:text-alarma-500 hover:underline whitespace-nowrap">
                                Quitar
                            </button>
                        </div>
                        @error("jefesAdicionales.{$indice}") <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    @empty
                        <p class="text-xs text-gray-500">Sin otros jefes inmediatos asignados.</p>
                    @endforelse
                </div>
            @endif

            <x-admin.barra-flotante>
                <a href="{{ route('unidades-organicas.index') }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </x-admin.barra-flotante>
        </form>
    </div>
</div>
