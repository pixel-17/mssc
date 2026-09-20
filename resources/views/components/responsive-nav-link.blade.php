@props(['active' => false, 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-2.5 w-full ps-3 pe-4 py-2.5 rounded-xl border-l-4 border-tinta-400 text-start text-base font-semibold text-tinta-900 dark:text-white bg-tinta-50 dark:bg-white/10 focus:outline-none transition duration-150 ease-in-out'
            : 'flex items-center gap-2.5 w-full ps-3 pe-4 py-2.5 rounded-xl border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-tinta-100/70 hover:text-tinta-800 dark:hover:text-white hover:bg-tinta-50/70 dark:hover:bg-white/10 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <span class="[&>svg]:size-5 shrink-0">{!! $icon !!}</span>
    @endif
    {{ $slot }}
</a>
