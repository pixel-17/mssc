<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$motivo ? 'Editar motivo' : 'Nuevo motivo'" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-admin.campo label="Nombre del motivo" for="motivo-form-nombre">
                    <x-input id="motivo-form-nombre" type="text" wire:model="nombre" class="w-full" placeholder="ej. Cita médica" />
                </x-admin.campo>

                <x-admin.campo label="Código (una palabra, sin espacios)" for="motivo-form-codigo">
                    <x-input id="motivo-form-codigo" type="text" wire:model="codigo" class="w-full" placeholder="ej. SALUD" />
                </x-admin.campo>

                <div class="sm:col-span-2">
                    <x-admin.campo-checkbox for="activo" wire:model="activo" label="Los trabajadores pueden elegir este motivo" />
                </div>
            </div>

            <div class="space-y-4">
                <x-admin.campo-checkbox for="requiereJustificacion" wire:model.live="requiereJustificacion"
                    label="Requiere justificación"
                    ayuda="El trabajador debe presentar un documento después de la salida (por ejemplo, un certificado médico). Si no lo presenta a tiempo, se le descuenta." />

                @if ($requiereJustificacion)
                    <x-admin.campo label="Horas hábiles para presentarla" for="motivo-form-plazo">
                        <x-input id="motivo-form-plazo" type="number" min="1" max="720" wire:model="plazoJustificacionHorasHabiles" class="w-full sm:w-48" placeholder="48" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Si lo dejas vacío se usa el plazo general (48 horas hábiles).</p>
                    </x-admin.campo>
                @else
                    <x-admin.campo-checkbox for="aplicaDescuento" wire:model="aplicaDescuento"
                        label="Aplica descuento"
                        ayuda="El tiempo que el trabajador esté fuera se resta de sus horas del mes." />
                @endif
            </div>

            <x-admin.barra-flotante>
                <a href="{{ route('motivos.index') }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </x-admin.barra-flotante>
        </form>
    </div>
</div>
