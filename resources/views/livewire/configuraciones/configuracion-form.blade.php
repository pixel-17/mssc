<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            Editar configuración
        </h2>

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Clave</label>
                <input type="text" value="{{ $configuracion->clave }}" disabled class="w-full rounded-md border-gray-300 dark:bg-gray-800 opacity-60">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Valor</label>
                <input type="text" wire:model="valor" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                @error('valor') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Descripción</label>
                <input type="text" value="{{ $configuracion->descripcion }}" disabled class="w-full rounded-md border-gray-300 dark:bg-gray-800 opacity-60">
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('configuraciones.index') }}" class="text-sm text-gray-500">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
