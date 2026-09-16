<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Calendario de turnos — {{ $trabajador->nombre_completo }}
            </h2>

            <div class="flex items-center gap-3">
                <button type="button" wire:click="mesAnterior" class="px-3 py-1.5 rounded-md border text-sm">
                    &larr; Anterior
                </button>
                <span class="text-sm font-semibold w-32 text-center">
                    {{ $inicioMes->translatedFormat('F Y') }}
                </span>
                <button type="button" wire:click="mesSiguiente" class="px-3 py-1.5 rounded-md border text-sm">
                    Siguiente &rarr;
                </button>
            </div>
        </div>

        <p class="text-sm text-gray-500">
            Solo lectura. Régimen {{ $trabajador->regimen }}.
            M = Mañana, T = Tarde, N = Noche, D = Descanso, DIA = horario ordinario (276).
        </p>

        <div class="glass-card p-4 overflow-x-auto">
            <table class="min-w-full text-center text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-2 py-2">Lun</th>
                        <th class="px-2 py-2">Mar</th>
                        <th class="px-2 py-2">Mié</th>
                        <th class="px-2 py-2">Jue</th>
                        <th class="px-2 py-2">Vie</th>
                        <th class="px-2 py-2">Sáb</th>
                        <th class="px-2 py-2">Dom</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($semanas as $semana)
                        <tr>
                            @foreach ($semana as $dia)
                                <td class="px-2 py-3 align-top {{ $dia ? 'border border-gray-100 dark:border-gray-800' : '' }}">
                                    @if ($dia)
                                        @php($turno = $turnosPorDia->get($dia))
                                        <div class="text-xs text-gray-400">{{ $dia }}</div>
                                        <div class="mt-1 font-semibold {{ $turno && $turno->es_descanso ? 'text-gray-400' : 'text-ocean-800 dark:text-ocean-300' }}">
                                            {{ $turno?->etiqueta() ?? '—' }}
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
