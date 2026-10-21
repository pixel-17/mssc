{{-- Respaldo del tiempo real: si Reverb está caído o el canal no aplica
     (Admin viendo a otro trabajador), se refresca cada 30 s solo mientras
     la vista está visible. --}}
<div wire:poll.30s.visible>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
                Calendario de turnos — {{ $trabajador->nombre_completo }}
            </h2>

            <div class="flex items-center gap-2">
                <button type="button" wire:click="mesAnterior" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" aria-label="Mes anterior">
                    &larr; Anterior
                </button>
                <button type="button" wire:click="irAHoy" class="px-3 py-1.5 rounded-md border text-sm font-semibold text-tinta-700 dark:text-tinta-300 hover:bg-tinta-50 dark:hover:bg-tinta-500/10 transition-colors">
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

        {{-- Un solo componente Alpine para todo el calendario: el detalle
             de cada día sale de $detalleDias (ya resuelto en el servidor
             con lo que el admin/jefe programó), y se muestra en UN panel
             junto a la leyenda en vez de un popup + x-data por celda.
             Más liviano: 1 componente y 1 bloque de detalle por mes, no
             hasta 31. --}}
        <div x-data="{ diaSeleccionado: null, detalles: @js($detalleDias) }">

            {{-- Leyenda: mismo color que las celdas de abajo, un vistazo basta
                 para saber qué tipo de turno es sin tener que leer la sigla.
                 El detalle del día elegido aparece acá mismo, a la derecha. --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (['M' => 'Mañana', 'T' => 'Tarde', 'N' => 'Noche', 'D' => 'Descanso', 'DIA' => 'Horario ordinario (276)'] as $sigla => $nombre)
                        <span wire:key="calendario-individual-index-span-{{ $sigla }}" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium
                            {{ \App\Support\TurnoColores::para($sigla) }}">
                            <span class="font-bold">{{ $sigla }}</span> · {{ $nombre }}
                        </span>
                    @endforeach
                </div>

                <div
                    x-show="diaSeleccionado !== null"
                    x-cloak
                    x-transition
                    class="inline-flex items-center gap-3 rounded-full border bg-white dark:bg-gray-900 dark:border-gray-700 px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 shadow-sm"
                >
                    <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="detalles[diaSeleccionado]?.titulo"></span>
                    <span x-show="detalles[diaSeleccionado]?.horario" x-text="detalles[diaSeleccionado]?.horario"></span>
                    <span x-show="detalles[diaSeleccionado]?.sede" class="text-gray-500 dark:text-gray-400" x-text="detalles[diaSeleccionado]?.sede"></span>
                    <button type="button" @click="diaSeleccionado = null" class="text-gray-400 hover:text-gray-600" aria-label="Cerrar detalle">&times;</button>
                </div>
            </div>

            <div class="glass-card p-4 overflow-x-auto mt-3">
                <table class="min-w-full text-center text-sm border-separate border-spacing-1">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                            <th scope="col" class="px-2 py-2">Lun</th>
                            <th scope="col" class="px-2 py-2">Mar</th>
                            <th scope="col" class="px-2 py-2">Mié</th>
                            <th scope="col" class="px-2 py-2">Jue</th>
                            <th scope="col" class="px-2 py-2">Vie</th>
                            <th scope="col" class="px-2 py-2">Sáb</th>
                            <th scope="col" class="px-2 py-2">Dom</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($semanas as $semana)
                            <tr wire:key="calendario-individual-index-tr-{{ $loop->index }}">
                                @foreach ($semana as $dia)
                                    <td wire:key="calendario-individual-index-td-{{ $loop->index }}" class="align-top p-0">
                                        @if ($dia)
                                            @php($turno = $turnosPorDia->get($dia))
                                            @php($esHoy = $inicioMes->copy()->day($dia)->isSameDay($hoy))
                                            <div
                                                @if ($turno) @click="diaSeleccionado = (diaSeleccionado === {{ $dia }} ? null : {{ $dia }})" @endif
                                                :class="diaSeleccionado === {{ $dia }} ? 'ring-2 ring-tinta-500 dark:ring-tinta-400' : ''"
                                                class="relative rounded-lg border px-2 py-2 h-16 flex flex-col justify-between transition-all
                                                    {{ $turno ? $turno->claseColor() : 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800' }}
                                                    {{ $turno ? 'cursor-pointer hover:shadow-md hover:-translate-y-0.5' : '' }}
                                                    {{ $esHoy ? 'ring-2 ring-tinta-500 dark:ring-tinta-400' : '' }}"
                                            >
                                                <div class="text-xs {{ $esHoy ? 'font-bold' : 'text-gray-500' }}">
                                                    {{ $dia }}
                                                </div>
                                                <div class="font-semibold text-right">
                                                    {{ $turno?->etiqueta() ?? '—' }}
                                                </div>
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
</div>
