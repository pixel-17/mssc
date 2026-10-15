@props(['titulo', 'descripcion' => null, 'peligro' => false])

{{--
    Tarjeta de sección: cabecera (título + descripción) y cuerpo en el slot.
    Es la base de <x-form-section> y <x-action-section>, y se puede usar sola
    en cualquier pantalla que agrupe contenido por secciones.

    Diseño plano: borde fino, sin barra de acento ni numeración; la
    jerarquía la da solo el espaciado y la tipografía.

    `peligro` tiñe la tarjeta con la paleta `alarma` (eliminar cuenta, etc.).
--}}
<section {{ $attributes->class([
    'overflow-hidden rounded-xl border shadow-sm scroll-mt-28',
    'border-tinta-100 bg-white dark:border-white/10 dark:bg-[var(--surface)]' => ! $peligro,
    'border-alarma-100 bg-white dark:border-alarma-500/30 dark:bg-[var(--surface)]' => $peligro,
]) }}>
    <header @class([
        'border-b px-5 py-4 sm:px-6',
        'border-tinta-100 dark:border-white/10' => ! $peligro,
        'border-alarma-100 bg-alarma-50/60 dark:border-alarma-500/30 dark:bg-alarma-500/10' => $peligro,
    ])>
        <x-section-title :peligro="$peligro">
            <x-slot name="title">{{ $titulo }}</x-slot>
            <x-slot name="description">{{ $descripcion }}</x-slot>
        </x-section-title>
    </header>

    {{ $slot }}
</section>
