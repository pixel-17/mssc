<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$feriado ? 'Editar feriado' : 'Nuevo feriado'" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <x-admin.campo label="Fecha" for="feriado-form-fecha">
                <x-input id="feriado-form-fecha" type="date" wire:model="fecha" class="w-full" />
            </x-admin.campo>

            <x-admin.campo label="Descripción" for="feriado-form-descripcion">
                <x-input id="feriado-form-descripcion" type="text" wire:model="descripcion" class="w-full" />
            </x-admin.campo>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('feriados.index') }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </div>
        </form>
    </div>
</div>
