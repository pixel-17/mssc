<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Editar configuración" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div>
                <x-label for="configuracion-form-clave" value="Clave" class="block mb-1" />
                <x-input id="configuracion-form-clave" type="text" :value="$configuracion->clave" disabled class="w-full opacity-60" />
            </div>

            @switch($this->tipoCampo())
                @case('hora')
                    <x-admin.campo label="Valor" for="configuracion-form-valor" campo="valor"
                        ayuda="Formato 24 horas (HH:MM), por ejemplo 07:45.">
                        <x-input id="configuracion-form-valor" type="time" wire:model="valor" class="w-full" />
                    </x-admin.campo>
                    @break

                @case('entero')
                    <x-admin.campo label="Valor" for="configuracion-form-valor" campo="valor">
                        <x-input id="configuracion-form-valor" type="number" min="1" max="100000" step="1" wire:model="valor" class="w-full" />
                    </x-admin.campo>
                    @break

                @case('dias')
                    <div>
                        <x-label for="configuracion-form-valor" value="Días laborables" class="block mb-2" />
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach (\App\Livewire\Configuraciones\ConfiguracionForm::DIAS as $numero => $nombre)
                                <x-admin.campo-checkbox :for="'dia-' . $numero" :label="$nombre"
                                    :value="$numero" wire:model.live="diasSeleccionados" />
                            @endforeach
                        </div>
                        <x-input-error for="valor" class="mt-2" />
                    </div>
                    @break

                @default
                    <x-admin.campo label="Valor" for="configuracion-form-valor" campo="valor">
                        <x-input id="configuracion-form-valor" type="text" wire:model="valor" class="w-full" />
                    </x-admin.campo>
            @endswitch

            <div>
                <x-label for="configuracion-form-descripcion" value="Descripción" class="block mb-1" />
                <x-input id="configuracion-form-descripcion" type="text" :value="$configuracion->descripcion" disabled class="w-full opacity-60" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('configuraciones.index') }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </div>
        </form>
    </div>
</div>