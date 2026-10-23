<x-form-section id="dispositivo" submit="guardar">
    <x-slot name="title">{{ __('Preferencias del dispositivo') }}</x-slot>
    <x-slot name="description">{{ __('Notificaciones, ubicación, cámara e instalación de la app en este dispositivo.') }}</x-slot>

    <x-slot name="form">
        <div class="col-span-6 divide-y divide-tinta-100 dark:divide-white/10">
            {{-- Notificaciones: activar/desactivar sigue en el botón del
                 menú (msscPushToggle); aquí solo el volumen del sonido
                 in-app. --}}
            <x-fila-ajuste x-data="msscVolumenNotificacion({{ \Illuminate\Support\Js::from($volumen) }})"
                           :titulo="__('Volumen de sonido de notificación')"
                           :descripcion="__('Qué tan fuerte suena el aviso dentro de la app.')">
                <x-slot name="detalle">
                    <div class="flex items-center gap-3">
                        <input id="volumen-notificacion" type="range" min="0" max="100" step="5"
                               aria-label="{{ __('Volumen de sonido de notificación') }}"
                               wire:model="volumen"
                               x-on:input="volumen = $event.target.value"
                               class="w-full accent-tinta-600 dark:accent-sello-500">
                        <span class="w-12 text-end text-sm tabular-nums text-tinta-600 dark:text-zinc-400" x-text="volumen + '%'"></span>
                    </div>

                    <button type="button" x-on:click="probar()"
                            class="mt-2 text-sm font-medium text-tinta-700 underline underline-offset-2 hover:text-tinta-950 dark:text-tinta-200 dark:hover:text-white">
                        {{ __('Probar sonido') }}
                    </button>

                    <x-input-error for="volumen" class="mt-2" />
                </x-slot>
            </x-fila-ajuste>

            <x-fila-ajuste x-data="msscGpsToggle()"
                        :titulo="__('Acceso a ubicación (GPS)')"
                        :descripcion="__('Permitir que la app use mi ubicación cuando la necesite')">
                <x-slot name="detalle">
                    <p class="text-xs text-tinta-600 dark:text-zinc-400" x-text="estado"></p>
                    <button type="button" x-on:click="pedirPermiso()" x-show="estado !== 'Permiso concedido'"
                            class="mt-1 text-sm font-medium text-tinta-700 underline underline-offset-2 hover:text-tinta-950 dark:text-tinta-200 dark:hover:text-white">
                        {{ __('Solicitar permiso al navegador') }}
                    </button>
                </x-slot>
            </x-fila-ajuste>

            <x-fila-ajuste x-data="msscCamaraToggle()"
                        :titulo="__('Acceso a la cámara')"
                        :descripcion="__('Permitir que la app use mi cámara cuando la necesite')">
                <x-slot name="detalle">
                    <p class="text-xs text-tinta-600 dark:text-zinc-400" x-text="estado"></p>
                    <button type="button" x-on:click="pedirPermiso()" x-show="estado !== 'Permiso concedido'"
                            class="mt-1 text-sm font-medium text-tinta-700 underline underline-offset-2 hover:text-tinta-950 dark:text-tinta-200 dark:hover:text-white">
                        {{ __('Solicitar permiso al navegador') }}
                    </button>
                </x-slot>
            </x-fila-ajuste>

            <x-fila-ajuste x-data="msscInstalarApp()" x-show="instalable"
                           :titulo="__('Instalar la app')"
                           :descripcion="__('Agrega MSSC a la pantalla de inicio de este dispositivo.')">
                <x-secondary-button type="button" x-on:click="instalar()">{{ __('Instalar app') }}</x-secondary-button>
            </x-fila-ajuste>
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message on="saved">{{ __('Guardado.') }}</x-action-message>
        <x-button>{{ __('Guardar') }}</x-button>
    </x-slot>
</x-form-section>