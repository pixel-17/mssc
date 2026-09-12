<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            {{ $motivo ? 'Editar motivo' : 'Nuevo motivo' }}
        </h2>

        <form wire:submit="guardar" class="glass-card p-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Código</label>
                    <input type="text" wire:model="codigo" class="w-full rounded-md border-gray-300 dark:bg-gray-800" placeholder="ej. PARTICULAR, SALUD, COMISION, EMERGENCIA">
                    @error('codigo') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Nombre</label>
                    <input type="text" wire:model="nombre" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('nombre') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Adjunto</label>
                    <select wire:model="adjunto" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="no">No lleva adjunto</option>
                        <option value="opcional">Opcional</option>
                        <option value="flexible">Flexible (puede o no traerlo)</option>
                        <option value="obligatorio">Obligatorio</option>
                    </select>
                    @error('adjunto') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <label class="flex items-center gap-2 mt-6">
                    <input type="checkbox" wire:model="activo" class="rounded">
                    <span class="text-sm">Activo</span>
                </label>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Reglas de negocio — estas banderas son las que consultan las Actions del flujo, no hay lógica por nombre de motivo.
                </p>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="sumaDescuento" class="rounded">
                    <span class="text-sm">Suma al contador mensual de descuento</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="permiteBypassAprobacion" class="rounded">
                    <span class="text-sm">Permite bypass total de aprobación (nace autorizada)</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="permiteCierreSinRetorno" class="rounded">
                    <span class="text-sm">Permite cerrar sin retorno físico</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="requiereSustentoEnRetorno" class="rounded">
                    <span class="text-sm">Requiere sustento al retorno (48h hábiles)</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="participaReglaExclusividad" class="rounded">
                    <span class="text-sm">Participa en la regla de exclusividad (carril normal)</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="esDestinoReclasificacion" class="rounded">
                    <span class="text-sm">Es el destino de reclasificación (Particular) — debe estar activo en exactamente un motivo</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('motivos.index') }}" class="text-sm text-gray-500">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
