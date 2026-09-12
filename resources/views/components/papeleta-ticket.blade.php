@props(['papeleta'])

<a
    href="{{ route('trabajador.papeletas.show', $papeleta) }}"
    class="block glass-card hover:shadow-glass-lg active:scale-[.99] transition-all duration-150"
>
    <div class="p-4 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-bold text-ocean-950 dark:text-white truncate">{{ $papeleta->motivo->nombre }}</p>
            <p class="text-sm text-gray-500 dark:text-ocean-100/60 mt-0.5 truncate">{{ $papeleta->sede->nombre ?? 'Sin sede asignada' }}</p>
        </div>
        <x-estado-papeleta :estado="$papeleta->estado" class="shrink-0 whitespace-nowrap" />
    </div>

    <div class="mx-4 border-t border-dashed border-ocean-200/70 dark:border-white/15"></div>

    <div class="px-4 py-3 flex items-center justify-between text-sm">
        <span class="text-gray-500 dark:text-ocean-100/50">
            {{ $papeleta->dia_operativo?->format('d/m/Y') ?? '—' }}
        </span>
        <span class="inline-flex items-center gap-1 text-ocean-600 dark:text-ocean-300 font-semibold">
            Ver detalle
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </span>
    </div>
</a>
