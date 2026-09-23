@props(['papeleta'])

{{--
    Fila de registro, no una tarjeta más. El folio (n.° de papeleta) va
    en serif como en un talonario real; la perforación punteada separa
    el "encabezado del talón" (motivo + folio) del "pie" (fecha), tal
    como el papel físico que este sistema reemplaza.
--}}
<a
    href="{{ route('trabajador.papeletas.show', $papeleta) }}"
    class="group block border-b-2 border-dashed border-tinta-300 dark:border-white/30 py-4 first:pt-0 last:border-b-0 hover:bg-tinta-50/60 dark:hover:bg-white/5 -mx-4 sm:-mx-5 px-4 sm:px-5 rounded-md active:opacity-70 transition-colors"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex items-baseline gap-2">
            <span class="font-mono text-xs text-tinta-400 dark:text-tinta-300/60 shrink-0">
                N.° {{ str_pad($papeleta->id, 4, '0', STR_PAD_LEFT) }}
            </span>
            <p class="font-display font-semibold text-tinta-950 dark:text-white truncate">
                {{ $papeleta->motivo->nombre }}
            </p>
        </div>
        <x-estado-papeleta :estado="$papeleta->estado" class="shrink-0 whitespace-nowrap" />
    </div>

    <div class="mt-1.5 flex items-center justify-between text-sm">
        <span class="text-gray-500 dark:text-tinta-100/50 truncate">
            {{ $papeleta->sede->nombre ?? 'Sin sede asignada' }}
        </span>
        <span class="text-gray-400 dark:text-tinta-100/40 shrink-0 ms-3">
            {{ $papeleta->dia_operativo?->format('d/m/Y') ?? '—' }}
        </span>
    </div>
</a>