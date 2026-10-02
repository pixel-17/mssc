@props(['columnas'])

{{--
    Shell de tabla de los índices del catálogo admin: glass-card +
    <table> + <thead> generado a partir de $columnas (incluir '' como
    último elemento para la columna de acciones, sin encabezado visible).
    El <tbody> es el slot — las filas (forelse/wire:key/@empty) se quedan
    en cada vista porque varían demasiado de un módulo a otro como para
    abstraerlas; usa <x-admin.fila-vacia :colspan="count($columnas)" />
    para el caso @empty.
--}}

<div class="glass-card overflow-x-auto">
    <table class="min-w-full divide-y divide-tinta-100 dark:divide-white/10">
        <thead>
            <tr class="text-left text-xs uppercase text-tinta-500 dark:text-tinta-200">
                @foreach ($columnas as $columna)
                    <th scope="col" class="px-4 py-3">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-tinta-100 dark:divide-white/10">
            {{ $slot }}
        </tbody>
    </table>
</div>
