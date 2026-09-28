@props(['disabled' => false])

{{--
    Complemento de <x-input> para <select>: mismo tratamiento visual
    (borde tinta, fondo con backdrop-blur, foco tinta-500) para que un
    filtro en <select> no se vea como un control de otro sistema al lado
    de un <x-input> de texto. Antes cada <select> de filtro llevaba su
    propia clase suelta (w-full rounded-md border-gray-300 dark:bg-gray-800).
--}}

<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-xl border-tinta-200 dark:border-white/15 bg-white/70 dark:bg-white/10 dark:text-white backdrop-blur focus:border-tinta-500 focus:ring-tinta-500 shadow-sm transition']) !!}>
    {{ $slot }}
</select>
