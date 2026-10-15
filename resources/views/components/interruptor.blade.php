@props(['etiqueta'])

{{-- Interruptor on/off accesible (checkbox real oculto). wire:model y demás atributos van al <input>. --}}
<label class="relative inline-flex shrink-0 cursor-pointer items-center">
    <input type="checkbox" aria-label="{{ $etiqueta }}" {{ $attributes->class('peer sr-only') }}>
    <span class="h-6 w-11 rounded-full bg-tinta-200 transition-colors dark:bg-white/20 peer-checked:bg-tinta-600 dark:peer-checked:bg-sello-500 peer-focus-visible:ring-2 peer-focus-visible:ring-tinta-500 peer-focus-visible:ring-offset-2 peer-disabled:opacity-50 after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
</label>
