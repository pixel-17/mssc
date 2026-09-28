@php
    // Santiago (Cusco) por defecto para una sede nueva (sin ubicación aún).
    $lat = $latitud ?? -13.5415;
    $lng = $longitud ?? -71.9840;
@endphp

<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$sede ? 'Editar sede' : 'Crear sede'" />

        <div class="glass-card p-6">
            <form wire:submit="guardar" class="space-y-6">
                <x-admin.campo label="Nombre" for="nombre">
                    <x-input id="nombre" type="text" class="w-full" wire:model="nombre" />
                </x-admin.campo>

                <x-admin.campo label="Dirección" for="direccion">
                    <x-input id="direccion" type="text" class="w-full" wire:model="direccion" />
                </x-admin.campo>

                {{-- Ubicación: solo se marca en el mapa, no hay inputs de
                     latitud/longitud a la vista. --}}
                <div wire:ignore x-data="msscMapaSede({
                        lat: {{ $lat }},
                        lng: {{ $lng }},
                        radio: {{ $radioMetros }},
                        zoom: {{ $latitud ? 16 : 14 }},
                    })" x-init="init()">
                    <x-label value="Ubicación (haz clic en el mapa o arrastra el marcador)" />
                    <div x-ref="mapa" style="height: 320px;" class="mt-1 rounded-lg border border-gray-300 dark:border-gray-600"></div>
                    <x-input-error for="latitud" class="mt-2" />
                    <x-input-error for="longitud" class="mt-2" />
                </div>

                <x-admin.campo
                    label="Radio permitido (metros)"
                    for="radioMetros"
                    ayuda="Radio alrededor de la sede dentro del cual el retorno se marca como &quot;dentro de radio&quot;. El círculo del mapa se ajusta al escribir."
                >
                    <x-input id="radioMetros" type="number" min="10" class="w-full" wire:model.live="radioMetros" />
                </x-admin.campo>

                <x-admin.campo-checkbox for="activo" wire:model="activo" label="Activa" />

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('sedes.index') }}" class="text-sm text-gray-600 dark:text-gray-400">
                        Cancelar
                    </a>
                    <x-button>
                        {{ $sede ? 'Guardar cambios' : 'Crear sede' }}
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>
