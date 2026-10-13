<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$unidad ? 'Editar unidad orgánica' : 'Nueva unidad orgánica'" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <x-admin.campo label="Nombre" for="unidad-organica-form-nombre" campo="nombre">
                <x-input id="unidad-organica-form-nombre" type="text" wire:model="nombre" class="w-full" />
            </x-admin.campo>

            <x-admin.campo label="Tipo" for="unidad-organica-form-tipo" campo="tipo" ayuda="Solo pinta el organigrama, no afecta el escalamiento de papeletas.">
                <x-select id="unidad-organica-form-tipo" wire:model="tipo">
                    <option value="">— (sin tipo) —</option>
                    @foreach (\App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::TIPOS as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-select>
            </x-admin.campo>

            <x-admin.campo label="Unidad padre" for="unidad-organica-form-parentId" campo="parentId">
                <x-select id="unidad-organica-form-parentId" wire:model="parentId">
                    <option value="">— (raíz) —</option>
                    @foreach ($padresDisponibles as $id => $nombrePadre)
                        <option value="{{ $id }}">{{ $nombrePadre }}</option>
                    @endforeach
                </x-select>
            </x-admin.campo>

            <x-admin.campo label="Jefe de la unidad" for="unidad-organica-form-jefeId" campo="jefeId">
                <x-select id="unidad-organica-form-jefeId" wire:model="jefeId">
                    <option value="">— (ninguno) —</option>
                    @foreach ($jefesDisponibles as $id => $nombreJefe)
                        <option value="{{ $id }}">{{ $nombreJefe }}</option>
                    @endforeach
                </x-select>
            </x-admin.campo>

            <x-admin.campo-checkbox for="activo" wire:model="activo" label="Activo" />

            <x-admin.barra-flotante>
                <a href="{{ route('unidades-organicas.index') }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </x-admin.barra-flotante>
        </form>
    </div>
</div>