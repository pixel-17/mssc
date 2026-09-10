<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Historial</h3>
    <ol class="space-y-3">
        @forelse ($papeleta->historial as $evento)
            <li class="text-sm border-l-2 border-gray-200 pl-3">
                <p class="text-gray-900">
                    <span class="font-medium">{{ $evento->actor?->nombre_completo ?? 'Sistema' }}</span>
                    @if ($evento->estado_anterior && $evento->estado_nuevo)
                        movió de <span class="font-medium">{{ $evento->estado_anterior }}</span> a <span class="font-medium">{{ $evento->estado_nuevo }}</span>
                    @elseif ($evento->estado_nuevo)
                        registró estado <span class="font-medium">{{ $evento->estado_nuevo }}</span>
                    @endif
                </p>
                @if ($evento->justificacion)
                    <p class="text-gray-500 italic">"{{ $evento->justificacion }}"</p>
                @endif
                <p class="text-xs text-gray-400">{{ $evento->created_at->format('d/m/Y H:i') }}</p>
            </li>
        @empty
            <li class="text-sm text-gray-400">Sin eventos registrados.</li>
        @endforelse
    </ol>
</div>
