@props(['label', 'ayuda' => null, 'for' => null])

{{--
    Checkbox + etiqueta de los formularios admin, usando <x-checkbox>
    (ya existía, con el tinta-600 de marca) en vez del <input
    type="checkbox" class="rounded"> suelto que tenían los 6 formularios
    (checkbox azul de sistema, sin relación con la paleta tinta/sello).
    wire:model y demás atributos se pasan tal cual a <x-checkbox>.
--}}

<label class="flex items-center gap-2">
    <x-checkbox :id="$for" {{ $attributes }} />
    <span class="text-sm">{{ $label }}</span>
</label>

@if ($ayuda)
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $ayuda }}</p>
@endif

@if ($for)
    <x-input-error :for="$for" class="mt-1" />
@endif
