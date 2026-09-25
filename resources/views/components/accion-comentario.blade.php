@props([
    'action',
    'label',
    'color' => 'gray',
    'field' => 'comentario',
    'minlength' => 5,
    'maxlength' => 2000,
    'placeholder' => 'Escribe un comentario (mínimo :min caracteres)...',
    'confirmText' => null,
    // Aviso opcional que se muestra dentro del modal, antes de confirmar
    // (por ejemplo, cuando esta acción puede tener una consecuencia que
    // no es obvia desde el botón, como agotar un tope de intentos).
    'aviso' => null,
    // Casilla opcional (nombre del campo y texto) que viaja junto al comentario.
    'opcion' => null,
    'opcionLabel' => null,
    'opcionMarcada' => true,
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
        enviando: false,
        minlength: {{ (int) $minlength }},
        confirmText: @js($confirmText),
        enviar(event) {
            if (this.texto.trim().length < this.minlength) {
                event.preventDefault();
                return;
            }
            if (this.confirmText && !confirm(this.confirmText)) {
                event.preventDefault();
                return;
            }
            this.enviando = true;
        },
    }"
    x-on:keydown.escape.window="if (! enviando) open = false"
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

        <div x-show="open" x-on:click="if (! enviando) open = false"
             class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-75"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-75" x-transition:leave-end="opacity-0">
        </div>

        <div x-show="open"
             class="relative mb-6 w-full sm:max-w-md sm:mx-auto bg-white dark:bg-zinc-900 dark:border dark:border-white/10 rounded-lg shadow-xl overflow-hidden"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

            <form method="POST" action="{{ $action }}" @submit="enviar($event)" class="p-4 sm:p-6 space-y-4">
                @csrf

                <h3 id="{{ $id }}-titulo" class="text-base font-semibold text-gray-800 dark:text-white">{{ $label }}</h3>

                @if ($aviso)
                    <p class="rounded-md border border-amber-300 dark:border-amber-400/30 bg-amber-50 dark:bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-200">
                        {{ $aviso }}
                    </p>
                @endif

                <div>
                    <textarea name="{{ $field }}" aria-labelledby="{{ $id }}-titulo" x-model="texto" rows="4" required minlength="{{ (int) $minlength }}" maxlength="{{ (int) $maxlength }}"
                              placeholder="{{ $placeholder }}"
                              class="block w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500"></textarea>
                    <p class="mt-1 text-xs text-gray-400">
                        <span x-text="texto.trim().length"></span>/{{ (int) $maxlength }} caracteres · mínimo {{ (int) $minlength }}
                    </p>
                </div>

                @if ($opcion)
                    <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="{{ $opcion }}" value="1" @checked($opcionMarcada)
                               class="mt-0.5 rounded border-gray-300 dark:border-white/15 dark:bg-white/5 text-tinta-600 focus:ring-tinta-500">
                        <span>{{ $opcionLabel }}</span>
                    </label>
                @endif

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" :disabled="enviando" @click="open = false"
                            class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-white/15 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-transparent hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-40 disabled:cursor-not-allowed">
                        Cancelar
                    </button>
                    <button type="submit" :disabled="texto.trim().length < minlength || enviando"
                            class="inline-flex justify-center items-center gap-1.5 px-4 py-2 border border-transparent rounded-md text-sm font-semibold text-white {{ $colores }} disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg x-show="enviando" x-cloak class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="enviando ? 'Enviando…' : 'Confirmar'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
