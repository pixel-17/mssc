{{-- Respaldo del tiempo real: si Reverb está caído o el canal no aplica
     (Admin viendo a otro trabajador), se refresca cada 30 s solo mientras
     la vista está visible. --}}
<div wire:poll.30s.visible>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Calendario de turnos — {{ $trabajador->nombre_completo }}
            </h2>

            <div class="flex items-center gap-2">
                <button type="button" wire:click="mesAnterior" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" aria-label="Mes anterior">
                    &larr; Anterior
                </button>
                <button type="button" wire:click="irAHoy" class="px-3 py-1.5 rounded-md border text-sm font-semibold text-ocean-700 dark:text-ocean-300 hover:bg-ocean-50 dark:hover:bg-ocean-500/10 transition-colors">
                    Hoy
                </button>
                <span class="text-sm font-semibold w-32 text-center capitalize">
                    {{ $inicioMes->translatedFormat('F Y') }}
                </span>
                <button type="button" wire:click="mesSiguiente" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" aria-label="Mes siguiente">
                    Siguiente &rarr;
                </button>
            </div>
        </div>

        <p class="text-sm text-gray-500">
            Solo lectura. Régimen {{ $trabajador->regimen }}. Toca un día con turno para ver el detalle.
        </p>

        {{-- Leyenda: mismo color que las celdas de abajo, un vistazo basta
             para saber qué tipo de turno es sin tener que leer la sigla. --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['M' => 'Mañana', 'T' => 'Tarde', 'N' => 'Noche', 'D' => 'Descanso', 'DIA' => 'Horario ordinario (276)'] as $sigla => $nombre)
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium
                    {{ \App\Support\TurnoColores::para($sigla) }}">
                    <span class="font-bold">{{ $sigla }}</span> · {{ $nombre }}
                </span>
            @endforeach
        </div>

        <div class="glass-card p-4 overflow-x-auto">
            <table class="min-w-full text-center text-sm border-separate border-spacing-1">
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
                <tbody>
                    @foreach ($semanas as $semana)
                        <tr>
                            @foreach ($semana as $dia)
                                <td class="align-top p-0">
                                    @if ($dia)
                                        @php($turno = $turnosPorDia->get($dia))
                                        @php($esHoy = $inicioMes->copy()->day($dia)->isSameDay($hoy))
                                        <div
                                            x-data="{ abierto: false }"
                                            @if ($turno) @click="abierto = !abierto" @click.outside="abierto = false" @endif
                                            class="relative rounded-lg border px-2 py-2 h-16 flex flex-col justify-between transition-all
                                                {{ $turno ? $turno->claseColor() : 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800' }}
                                                {{ $turno ? 'cursor-pointer hover:shadow-md hover:-translate-y-0.5' : '' }}
                                                {{ $esHoy ? 'ring-2 ring-ocean-500 dark:ring-ocean-400' : '' }}"
                                        >
                                            <div class="text-xs {{ $esHoy ? 'font-bold' : 'text-gray-400' }}">
                                                {{ $dia }}
                                            </div>
                                            <div class="font-semibold text-right">
                                                {{ $turno?->etiqueta() ?? '—' }}
                                            </div>

                                            @if ($turno)
                                                <div
                                                    x-show="abierto"
                                                    x-cloak
                                                    x-transition
                                                    class="absolute z-10 top-full left-0 mt-1 w-48 rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-700 shadow-lg p-3 text-left text-xs text-gray-700 dark:text-gray-300"
                                                >
                                                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $turno->nombreTurno() }}</p>
                                                    @if (! $turno->es_descanso && $turno->hora_inicio && $turno->hora_fin)
                                                        <p class="mt-1">{{ substr($turno->hora_inicio, 0, 5) }} – {{ substr($turno->hora_fin, 0, 5) }}</p>
                                                    @endif
                                                    @if ($turno->sede)
                                                        <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $turno->sede->nombre }}</p>
                                                    @endif
                                                </div>
                                            @endif
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
