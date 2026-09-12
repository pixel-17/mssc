@props(['active' => false, 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'group inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-white bg-white/15 backdrop-blur border border-white/20 shadow-inner transition duration-150 ease-in-out'
            : 'group inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium text-white/70 hover:text-white hover:bg-white/10 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <span class="[&>svg]:size-4.5 [&>svg]:size-[18px]">{!! $icon !!}</span>
    @endif
    {{ $slot }}
</a>
