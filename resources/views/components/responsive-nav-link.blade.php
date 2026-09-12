@props(['active' => false, 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-2.5 w-full ps-3 pe-4 py-2.5 rounded-xl border-l-4 border-ocean-400 text-start text-base font-semibold text-ocean-900 dark:text-white bg-ocean-50 dark:bg-white/10 focus:outline-none transition duration-150 ease-in-out'
            : 'flex items-center gap-2.5 w-full ps-3 pe-4 py-2.5 rounded-xl border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-ocean-100/70 hover:text-ocean-800 dark:hover:text-white hover:bg-ocean-50/70 dark:hover:bg-white/10 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <span class="[&>svg]:size-5 shrink-0">{!! $icon !!}</span>
    @endif
    {{ $slot }}
</a>
