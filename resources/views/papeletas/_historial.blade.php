<div class="glass-card p-6">
    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Historial</h3>
    <ol class="space-y-3">
        @forelse ($papeleta->historial as $evento)
            @php
                // Los eventos guardan solo el basename del estado (p. ej.
                // "Rechazada"); reconstruimos el FQCN para reusar el mismo
                // mapa de colores/etiquetas que ya usa <x-estado-papeleta>,
                // así un evento del historial se ve igual que el badge de
                // estado actual en toda la app.
                $puntoNuevo = null;
                $etiquetaNuevo = $evento->estado_nuevo;
                $etiquetaAnterior = $evento->estado_anterior;

                if ($evento->estado_nuevo) {
                    [$etiquetaNuevo, , $puntoNuevo] = \App\Support\PapeletaEstadoPresentacion::para('App\\States\\Papeleta\\'.$evento->estado_nuevo);
                }
                if ($evento->estado_anterior) {
                    [$etiquetaAnterior] = \App\Support\PapeletaEstadoPresentacion::para('App\\States\\Papeleta\\'.$evento->estado_anterior);
                }
            @endphp
            <li class="text-sm border-l-2 border-gray-200 dark:border-white/10 pl-3">
                <p class="text-gray-900 dark:text-white flex items-center gap-1.5 flex-wrap">
                    @if ($puntoNuevo)
                        <span class="size-1.5 rounded-full shrink-0 {{ $puntoNuevo }}"></span>
                    @endif
                    <span class="font-medium">{{ $evento->actor?->nombre_completo ?? 'Sistema' }}</span>
                    @if ($evento->estado_anterior && $evento->estado_nuevo)
                        movió de <span class="font-medium">{{ $etiquetaAnterior }}</span> a <span class="font-medium">{{ $etiquetaNuevo }}</span>
                    @elseif ($evento->estado_nuevo)
                        registró estado <span class="font-medium">{{ $etiquetaNuevo }}</span>
                    @endif
                </p>
                @if ($evento->justificacion)
                    <p class="text-gray-500 dark:text-tinta-100/60 italic">"{{ $evento->justificacion }}"</p>
                @endif
                <p class="text-xs text-gray-400 dark:text-tinta-100/40">{{ $evento->created_at->format('d/m/Y H:i') }}</p>
            </li>
        @empty
            <li class="text-sm text-gray-400 dark:text-tinta-100/40">Sin eventos registrados.</li>
        @endforelse
    </ol>
</div>
