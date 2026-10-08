@props(['estado', 'abandono' => false])

@php
    // Una papeleta con causa de abandono se muestra distinta de una normal.
    [$etiqueta, $colores, $punto] = ($abandono ? \App\Support\PapeletaEstadoPresentacion::porAbandono($estado) : null)
        ?? \App\Support\PapeletaEstadoPresentacion::para($estado);
@endphp

<span {{ $attributes->merge(['class' => "badge-tinta $colores"]) }}>
    <span class="size-1.5 rounded-full {{ $punto }}"></span>
    {{ $etiqueta }}
</span>
