@props(['href'])

{{--
    "+ Nuevo X" en el encabezado de cada índice. Antes era un <a> con
    Tailwind suelto (bg-tinta-800 rounded-md) en vez de .btn-primary — el
    mismo botón que <x-button> usa en el resto del sistema.
--}}

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn-primary']) }}>
    <x-icon name="plus-circle" class="size-4" />
    {{ $slot }}
</a>
