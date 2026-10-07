@props(['estado', 'abandono' => false])

@php
    // Una papeleta Cerrada por abandono se muestra distinta de una cerrada normal.
    $esCerrada = ($estado instanceof \App\States\Papeleta\Cerrada) || $estado === \App\States\Papeleta\Cerrada::class;
    [$etiqueta, $colores, $punto] = ($abandono && $esCerrada)
        ? \App\Support\PapeletaEstadoPresentacion::cerradaPorAbandono()
        : \App\Support\PapeletaEstadoPresentacion::para($estado);
@endphp

<span {{ $attributes->merge(['class' => "badge-tinta $colores"]) }}>
    <span class="size-1.5 rounded-full {{ $punto }}"></span>
    {{ $etiqueta }}
</span>
