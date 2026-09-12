@props(['label', 'value', 'hint' => null, 'href' => null, 'icon' => null, 'tono' => 'ocean'])

@php
    $tonos = [
        'ocean' => 'bg-ocean-500/15 text-ocean-700 dark:text-ocean-300',
        'amber' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        'red' => 'bg-red-500/15 text-red-700 dark:text-red-300',
        'emerald' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        'purple' => 'bg-purple-500/15 text-purple-700 dark:text-purple-300',
    ];
    $tonoClases = $tonos[$tono] ?? $tonos['ocean'];
@endphp

<div {{ $attributes->merge(['class' => 'stat-card relative '.($href ? 'hover:shadow-glass-lg transition-shadow duration-150' : '')]) }}>
    @if ($href)
        <a href="{{ $href }}" class="absolute inset-0 rounded-2xl" aria-label="{{ $label }}"></a>
    @endif

    <div class="relative">
        <p class="stat-card-label">{{ $label }}</p>
        <p class="stat-card-value mt-1">{{ $value ?? '—' }}</p>
        @if ($hint)
            <p class="text-xs text-gray-500 dark:text-ocean-100/50 mt-1">{{ $hint }}</p>
        @endif
    </div>

    @if ($icon)
        <span class="icon-chip !size-11 {{ $tonoClases }} shrink-0 relative">
            {!! $icon !!}
        </span>
    @endif
</div>
