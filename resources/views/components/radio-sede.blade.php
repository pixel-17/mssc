@props(['dentro' => null])

{{-- Verde = dentro del radio de la sede, rojo = fuera, gris = sin ubicación (retorno manual o sede sin coordenadas). --}}
@if (is_null($dentro))
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300']) }}>
        <span class="size-1.5 rounded-full bg-gray-400"></span> Sin ubicación
    </span>
@elseif ($dentro)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300']) }}>
        <span class="size-1.5 rounded-full bg-emerald-500"></span> Dentro del radio
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300']) }}>
        <span class="size-1.5 rounded-full bg-red-500"></span> Fuera del radio
    </span>
@endif
