@props(['label', 'for'])

{{--
    Envoltorio label + control para las barras de filtro de los índices
    (buscador de Usuarios, "Filtrar por trabajador" de Turnos). El control
    en sí (x-input o x-select) va en el slot, para no forzar un solo tipo
    de campo.
--}}

<div class="max-w-xs flex-1 min-w-[11rem]">
    <x-label :for="$for" :value="$label" class="block mb-1" />

    {{ $slot }}
</div>
