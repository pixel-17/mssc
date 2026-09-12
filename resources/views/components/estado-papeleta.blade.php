@props(['estado'])

@php
    [$etiqueta, $colores, $punto] = \App\Support\PapeletaEstadoPresentacion::para($estado);
@endphp

<span {{ $attributes->merge(['class' => "badge-ocean $colores"]) }}>
    <span class="size-1.5 rounded-full {{ $punto }}"></span>
    {{ $etiqueta }}
</span>
