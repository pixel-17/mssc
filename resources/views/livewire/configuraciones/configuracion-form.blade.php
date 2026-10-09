<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Editar configuración" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div>
                <x-label for="configuracion-form-clave" value="Clave" class="block mb-1" />
                <x-input id="configuracion-form-clave" type="text" :value="$configuracion->clave" disabled class="w-full opacity-60" />
            </div>

            <x-admin.campo label="Valor" for="configuracion-form-valor" campo="valor">
                <x-input id="configuracion-form-valor" type="text" wire:model="valor" class="w-full" />
            </x-admin.campo>

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