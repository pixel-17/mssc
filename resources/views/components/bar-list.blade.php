@props(['items', 'vacio' => 'Sin datos en este periodo.'])

@php
    $maximo = collect($items)->max('total') ?: 1;
@endphp

<div class="space-y-3">
    @forelse ($items as $item)
        <div>
            <div class="flex items-baseline justify-between gap-3 mb-1">
                <span class="text-sm font-medium text-gray-700 dark:text-ocean-50/80 truncate">{{ $item->etiqueta }}</span>
                <span class="text-sm font-semibold text-ocean-950 dark:text-white shrink-0">{{ $item->total }}</span>
            </div>
            <div class="h-2 rounded-full bg-ocean-100 dark:bg-white/10 overflow-hidden">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-ocean-400 to-ocean-600"
                    style="width: {{ max(6, (int) round($item->total / $maximo * 100)) }}%"
                ></div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-ocean-100/50">{{ $vacio }}</p>
    @endforelse
</div>
