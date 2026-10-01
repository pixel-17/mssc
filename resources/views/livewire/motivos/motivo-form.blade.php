<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$motivo ? 'Editar motivo' : 'Nuevo motivo'" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-admin.campo label="Código" for="motivo-form-codigo">
                    <x-input id="motivo-form-codigo" type="text" wire:model="codigo" class="w-full" placeholder="ej. PARTICULAR, SALUD, COMISION" />
                </x-admin.campo>

                <x-admin.campo label="Nombre" for="motivo-form-nombre">
                    <x-input id="motivo-form-nombre" type="text" wire:model="nombre" class="w-full" />
                </x-admin.campo>

                <x-admin.campo label="Adjunto" for="motivo-form-adjunto">
                    <x-select id="motivo-form-adjunto" wire:model="adjunto">
                        <option value="no">No lleva adjunto</option>
                        <option value="opcional">Opcional</option>
                        <option value="flexible">Flexible (puede o no traerlo)</option>
                        <option value="obligatorio">Obligatorio</option>
                    </x-select>
                </x-admin.campo>

                <div class="sm:mt-6">
                    <x-admin.campo-checkbox for="activo" wire:model="activo" label="Activo" />
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Reglas de negocio — estas banderas son las que consultan las Actions del flujo, no hay lógica por nombre de motivo.
                </p>

                <x-admin.campo-checkbox for="sumaDescuento" wire:model="sumaDescuento" label="Suma al contador mensual de descuento" />
                <x-admin.campo-checkbox for="permiteCierreSinRetorno" wire:model="permiteCierreSinRetorno" label="Permite cerrar sin retorno físico" />
                <x-admin.campo-checkbox for="requiereSustentoEnRetorno" wire:model="requiereSustentoEnRetorno" label="Requiere sustento al retorno (48h hábiles)" />
                <x-admin.campo-checkbox for="esDestinoReclasificacion" wire:model="esDestinoReclasificacion" label="Es el destino de reclasificación (Particular) — debe estar activo en exactamente un motivo" />
            </div>

            <x-admin.barra-flotante>
                <a href="{{ route('motivos.index') }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </x-admin.barra-flotante>
        </form>
    </div>
</div>
