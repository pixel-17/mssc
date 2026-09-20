{{--
    Link de navegación del sidebar. Usado tanto en el drawer móvil como
    en la barra fija de escritorio (misma lista, sin duplicar markup).
    El icono va en un "chip" para que se lea bien incluso en modo
    colapsado (solo iconos) y para dar peso visual consistente.
--}}
@props(['active' => false, 'icon' => null])

@php
$iconChip = ($active ?? false)
    ? 'bg-tinta-600 text-white shadow-tinta-glow'
    : 'bg-tinta-50 dark:bg-white/5 text-tinta-600 dark:text-tinta-300 group-hover:bg-tinta-100 dark:group-hover:bg-white/10';

$label = ($active ?? false)
    ? 'text-tinta-900 dark:text-white'
    : 'text-gray-600 dark:text-tinta-100/80 group-hover:text-tinta-900 dark:group-hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold transition']) }}>
    @if ($icon)
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg transition [&>svg]:size-5 {{ $iconChip }}">
            {!! $icon !!}
        </span>
    @endif

    <span class="truncate {{ $label }}" x-bind:class="$store.sidebar.collapsed && 'lg:hidden'">
        {{ $slot }}
    </span>

    @if ($active ?? false)
        <span class="ms-auto hidden size-1.5 shrink-0 rounded-full bg-sello-500 lg:block" x-show="!$store.sidebar.collapsed" x-cloak></span>
    @endif
</a>
