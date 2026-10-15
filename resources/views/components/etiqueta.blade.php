@props(['tono' => 'tinta', 'punto' => false])

{{-- Pastilla de texto corto. tono: tinta | sello | registro | alarma | gris --}}
@php
    $tonos = [
        'tinta'    => 'bg-tinta-100 text-tinta-700 ring-tinta-200 dark:bg-white/10 dark:text-tinta-100 dark:ring-white/15',
        'sello'    => 'bg-sello-50 text-sello-700 ring-sello-200 dark:bg-sello-500/15 dark:text-sello-200 dark:ring-sello-400/30',
        'registro' => 'bg-registro-100 text-registro-700 ring-registro-500/30 dark:bg-registro-500/20 dark:text-registro-100 dark:ring-registro-500/40',
        'alarma'   => 'bg-alarma-50 text-alarma-700 ring-alarma-100 dark:bg-alarma-500/15 dark:text-alarma-100 dark:ring-alarma-500/30',
        'gris'     => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset', $tonos[$tono] ?? $tonos['tinta']]) }}>
    @if ($punto)<span class="size-1.5 rounded-full bg-current opacity-70"></span>@endif
    {{ $slot }}
</span>
