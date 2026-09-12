@php
    // Lima por defecto para una sede nueva (sin ubicación aún).
    $lat = $latitud ?? -12.0464;
    $lng = $longitud ?? -77.0428;
@endphp

<div>
    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            {{ $sede ? 'Editar sede' : 'Crear sede' }}
        </h2>

        <div class="glass-card p-6">
            <form wire:submit="guardar" class="space-y-6">
                <div>
                    <x-label for="nombre" value="Nombre" />
                    <x-input id="nombre" type="text" class="mt-1 block w-full" wire:model="nombre" />
                    <x-input-error for="nombre" class="mt-2" />
                </div>

                <div>
                    <x-label for="direccion" value="Dirección" />
                    <x-input id="direccion" type="text" class="mt-1 block w-full" wire:model="direccion" />
                    <x-input-error for="direccion" class="mt-2" />
                </div>

                {{-- Ubicación: solo se marca en el mapa, no hay inputs de
                     latitud/longitud a la vista. --}}
                <div wire:ignore x-data="msscMapaSede({
                        lat: {{ $lat }},
                        lng: {{ $lng }},
                        radio: {{ $radioMetros }},
                        zoom: {{ $latitud ? 16 : 12 }},
                    })" x-init="init()">
                    <x-label value="Ubicación (haz clic en el mapa o arrastra el marcador)" />
                    <div x-ref="mapa" style="height: 320px;" class="mt-1 rounded-lg border border-gray-300 dark:border-gray-600"></div>
                    <x-input-error for="latitud" class="mt-2" />
                    <x-input-error for="longitud" class="mt-2" />
                </div>

                <div>
                    <x-label for="radioMetros" value="Radio permitido (metros)" />
                    <x-input id="radioMetros" type="number" min="10" class="mt-1 block w-full" wire:model.live="radioMetros" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Radio alrededor de la sede dentro del cual el retorno se marca como "dentro de radio". El círculo del mapa se ajusta al escribir.
                    </p>
                    <x-input-error for="radioMetros" class="mt-2" />
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="activo" wire:model="activo" class="rounded">
                    <x-label for="activo" value="Activa" />
                </div>

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
