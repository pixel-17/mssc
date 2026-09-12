@props([
    'action',
    'label',
    'color' => 'gray',
    'field' => 'comentario',
    'minlength' => 5,
    'placeholder' => 'Escribe un comentario (mínimo :min caracteres)...',
    'confirmText' => null,
])

@php
    $id = 'accion-comentario-'.md5($action.$label);

    $colores = [
        'red' => 'bg-red-600 hover:bg-red-700',
        'orange' => 'bg-orange-500 hover:bg-orange-600',
        'green' => 'bg-green-600 hover:bg-green-700',
        'gray' => 'bg-gray-600 hover:bg-gray-700',
    ][$color] ?? 'bg-gray-600 hover:bg-gray-700';

    $placeholder = str_replace(':min', (string) $minlength, $placeholder);
@endphp

<div x-data="{
        open: false,
        texto: '',
        minlength: {{ (int) $minlength }},
        confirmText: @js($confirmText),
        enviar(event) {
            if (this.texto.trim().length < this.minlength) {
                return;
            }
            if (this.confirmText && !confirm(this.confirmText)) {
                event.preventDefault();
            }
        },
    }"
    x-on:keydown.escape.window="open = false"
    class="inline-block"
>
    {{-- Botón que abre el modal --}}
    <button type="button" @click="open = true"
            class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-semibold rounded-md text-white {{ $colores }}">
        {{ $label }}
    </button>

    {{-- Modal --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
         role="dialog" aria-modal="true" :aria-labelledby="'{{ $id }}-titulo'">

        <div x-show="open" x-on:click="open = false"
             class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-75"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-75" x-transition:leave-end="opacity-0">
        </div>

        <div x-show="open"
             class="relative mb-6 w-full sm:max-w-md sm:mx-auto bg-white rounded-lg shadow-xl overflow-hidden"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

            <form method="POST" action="{{ $action }}" @submit="enviar($event)" class="p-4 sm:p-6 space-y-4">
                @csrf

                <h3 id="{{ $id }}-titulo" class="text-base font-semibold text-gray-800">{{ $label }}</h3>

                <div>
                    <textarea name="{{ $field }}" x-model="texto" rows="4" required minlength="{{ (int) $minlength }}" maxlength="2000"
                              placeholder="{{ $placeholder }}"
                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <p class="mt-1 text-xs text-gray-400">
                        <span x-text="texto.trim().length"></span>/{{ (int) $minlength }} caracteres mínimos
                    </p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" @click="open = false"
                            class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" :disabled="texto.trim().length < minlength"
                            class="inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md text-sm font-semibold text-white {{ $colores }} disabled:opacity-40 disabled:cursor-not-allowed">
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
