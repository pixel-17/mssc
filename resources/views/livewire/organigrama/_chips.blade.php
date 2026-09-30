{{-- Régimen y sede de una persona. Rojo = sin sede; ámbar = sede distinta a la de su jefe. --}}
<span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] text-gray-700 dark:bg-white/10 dark:text-tinta-50/80">{{ $persona->regimen ?? '—' }}</span>

@if ($persona->sede_id === null)
    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:bg-red-500/20 dark:text-red-300">
        <span class="inline-block size-1.5 rounded-full bg-red-500"></span> Sin sede
    </span>
@elseif ($sedeReferencia !== null && (int) $persona->sede_id !== (int) $sedeReferencia)
    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300" title="Sede distinta a la de su jefe">
        <span class="inline-block size-1.5 rounded-full bg-amber-500"></span> {{ $persona->sede?->nombre }}
    </span>
@else
    <span class="rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100">{{ $persona->sede?->nombre }}</span>
@endif
