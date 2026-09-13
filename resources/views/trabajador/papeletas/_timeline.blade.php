<div class="glass-card p-4">
    <h3 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80 mb-4">Historial</h3>

    <ol class="relative border-l-2 border-ocean-100 dark:border-white/10 ml-2 space-y-5">
        @forelse ($papeleta->historial as $evento)
            <li class="pl-5 relative">
                <span class="absolute -left-[9px] top-0.5 size-4 rounded-full bg-white dark:bg-ocean-950 border-2 border-ocean-400 dark:border-ocean-400"></span>

                <p class="text-sm text-ocean-950 dark:text-white">
                    <span class="font-semibold">{{ $evento->actor?->nombre_completo ?? 'Sistema' }}</span>
                    @if ($evento->estado_anterior && $evento->estado_nuevo)
                        movió de <span class="font-medium">{{ $evento->estado_anterior }}</span> a <span class="font-medium">{{ $evento->estado_nuevo }}</span>
                    @elseif ($evento->estado_nuevo)
                        registró estado <span class="font-medium">{{ $evento->estado_nuevo }}</span>
                    @endif
                </p>

                @if ($evento->justificacion)
                    <p class="text-sm text-gray-500 dark:text-ocean-100/60 italic mt-0.5">"{{ $evento->justificacion }}"</p>
                @endif

                <p class="text-xs text-gray-400 dark:text-ocean-100/40 mt-1">{{ $evento->created_at->format('d/m/Y H:i') }}</p>
            </li>
        @empty
            <li class="pl-5 text-sm text-gray-400 dark:text-ocean-100/40">Sin eventos registrados.</li>
        @endforelse
    </ol>
</div>
