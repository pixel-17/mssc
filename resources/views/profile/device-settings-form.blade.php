<x-form-section submit="guardar">
    <x-slot name="title">
        {{ __('Preferencias del dispositivo') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Notificaciones, ubicación, cámara e instalación de la app en este dispositivo.') }}
    </x-slot>

    <x-slot name="form">
        {{-- Notificaciones: activar/desactivar sigue en el botón del
             menú (msscPushToggle); aquí solo el volumen del sonido
             in-app. --}}
        <div class="col-span-6 sm:col-span-4" x-data="msscVolumenNotificacion(@js($volumen))">
            <x-label value="{{ __('Volumen de sonido de notificación') }}" />

            <div class="flex items-center gap-3 mt-1">
                <input
                    type="range"
                    min="0"
                    max="100"
                    step="5"
                    wire:model="volumen"
                    x-on:input="volumen = $event.target.value"
                    class="w-full"
                >
                <span class="text-sm text-gray-600 dark:text-gray-400 w-10 text-right" x-text="volumen + '%'"></span>
            </div>

            <button
                type="button"
                x-on:click="probar()"
                class="mt-2 text-sm text-ocean-700 dark:text-ocean-300 underline"
            >
                {{ __('Probar sonido') }}
            </button>

            <x-input-error for="volumen" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4" x-data="msscGpsToggle()">
            <x-label value="{{ __('Acceso a ubicación (GPS)') }}" />

            <div class="flex items-center gap-3 mt-1">
                <input type="checkbox" wire:model="permiteGps" id="permite_gps" class="rounded">
                <label for="permite_gps" class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Permitir que la app use mi ubicación cuando la necesite') }}
                </label>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="estado"></p>

            <button
                type="button"
                x-on:click="pedirPermiso()"
                x-show="estado !== 'Permiso concedido'"
                class="mt-1 text-sm text-ocean-700 dark:text-ocean-300 underline"
            >
                {{ __('Solicitar permiso al navegador') }}
            </button>
        </div>

        <div class="col-span-6 sm:col-span-4" x-data="msscCamaraToggle()">
            <x-label value="{{ __('Acceso a la cámara') }}" />

            <div class="flex items-center gap-3 mt-1">
                <input type="checkbox" wire:model="permiteCamara" id="permite_camara" class="rounded">
                <label for="permite_camara" class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Permitir que la app use mi cámara cuando la necesite') }}
                </label>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="estado"></p>

            <button
                type="button"
                x-on:click="pedirPermiso()"
                x-show="estado !== 'Permiso concedido'"
                class="mt-1 text-sm text-ocean-700 dark:text-ocean-300 underline"
            >
                {{ __('Solicitar permiso al navegador') }}
            </button>
        </div>

        <div class="col-span-6 sm:col-span-4" x-data="msscInstalarApp()" x-show="instalable">
            <x-label value="{{ __('Instalar la app') }}" />

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Agrega MSSC a la pantalla de inicio de este dispositivo.') }}
            </p>

            <button
                type="button"
                x-on:click="instalar()"
                class="mt-2 inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm"
            >
                {{ __('Instalar app') }}
            </button>
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Guardado.') }}
        </x-action-message>

        <x-button>
            {{ __('Guardar') }}
        </x-button>
    </x-slot>
</x-form-section>
