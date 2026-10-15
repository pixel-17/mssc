@props(['titulo', 'descripcion' => null])

{{--
    Fila de una lista de ajustes: texto a la izquierda, control a la derecha
    (en móvil el control baja debajo). Úsala dentro de un contenedor
    `divide-y` para que las filas queden separadas por una línea fina.
    $detalle (opcional): contenido extra a todo el ancho bajo la fila.
--}}
<div {{ $attributes->class('py-4 first:pt-0 last:pb-0') }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-tinta-950 dark:text-white">{{ $titulo }}</p>
            @if ($descripcion)
                <p class="mt-0.5 text-sm text-tinta-600 dark:text-zinc-400">{{ $descripcion }}</p>
            @endif
        </div>

        @if ($slot->isNotEmpty())
            <div class="shrink-0">{{ $slot }}</div>
        @endif
    </div>

    @isset($detalle)
        <div class="mt-3">{{ $detalle }}</div>
    @endisset
</div>
