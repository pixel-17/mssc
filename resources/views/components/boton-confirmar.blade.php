@props([
    'action',
    'label',
    'confirmText' => null,
    'color' => 'green',
    'loadingLabel' => null,
])

@php
    // Mismos tonos que x-accion-comentario, para que "Aprobar" no se vea
    // desentonado junto a "Observar"/"Rechazar" en la misma fila.
    $colores = [
        'red' => 'bg-red-600 hover:bg-red-700',
        'orange' => 'bg-orange-500 hover:bg-orange-600',
        'green' => 'bg-green-600 hover:bg-green-700',
        'gray' => 'bg-gray-600 hover:bg-gray-700',
    ][$color] ?? 'bg-green-600 hover:bg-green-700';

    $loadingLabel ??= $label.'...';
@endphp

{{--
    Acción de un clic (sin comentario) pero con la misma fricción mínima
    que sus vecinas: confirmación antes de enviar y estado "enviando..."
    para que no se pueda disparar dos veces con doble clic mientras la
    página navega tras el POST.
--}}
<form
    method="POST"
    action="{{ $action }}"
    x-data="{ enviando: false }"
    x-on:submit="if ({{ $confirmText ? 'true' : 'false' }} && !confirm(@js($confirmText))) { $event.preventDefault(); return; } enviando = true"
>
    @csrf

    <button
        type="submit"
        :disabled="enviando"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-transparent text-xs font-semibold rounded-md text-white {{ $colores }} disabled:opacity-60 disabled:cursor-not-allowed"
    >
        <svg x-show="enviando" x-cloak class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span x-text="enviando ? @js($loadingLabel) : @js($label)"></span>
    </button>
</form>
