{{--
    Calendario real del mes en curso para $trabajador, con la MISMA
    tabla y paleta que calendario-individual-index.blade.php. No es
    una previsualización calculada: lee los registros `turnos` que ya
    existen (ver ConfiguracionTurnoForm::calendarioDelMes()), así que
    un día sin turno cargado queda en blanco y, apenas se guarda, se
    repinta solo con el color real de lo que se generó.

    Variables esperadas: $inicioMes (Carbon), $semanas (array de
    semanas con días 1..n o null), $turnosPorDia (Collection<int,
    Turno> keyBy día), $hoy (Carbon).
--}}
<div>
    <div class="flex flex-wrap items-center gap-2 mb-3">
        @foreach (['M' => 'Mañana', 'T' => 'Tarde', 'N' => 'Noche', 'D' => 'Descanso', 'DIA' => 'Horario ordinario (276)'] as $sigla => $nombre)
            <span wire:key="calendario-mes-leyenda-{{ $sigla }}" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium
                {{ \App\Support\TurnoColores::para($sigla) }}">
                <span class="font-bold">{{ $sigla }}</span> · {{ $nombre }}
            </span>
        @endforeach
    </div>

    <div class="glass-card p-4 overflow-x-auto">
        <table class="min-w-full text-center text-sm border-separate border-spacing-1">
            <thead>
                <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $nombreDia)
                        <th scope="col" class="px-2 py-2">{{ $nombreDia }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($semanas as $semana)
                    <tr wire:key="calendario-mes-tr-{{ $loop->index }}">
                        @foreach ($semana as $dia)
                            <td wire:key="calendario-mes-td-{{ $loop->parent->index }}-{{ $loop->index }}" class="align-top p-0">
                                @if ($dia)
                                    @php($turno = $turnosPorDia->get($dia))
                                    @php($esHoy = $inicioMes->copy()->day($dia)->isSameDay($hoy))
                                    <div class="relative rounded-lg border px-2 py-2 h-16 flex flex-col justify-between transition-colors
                                        {{ $turno ? $turno->claseColor() : 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800' }}
                                        {{ $esHoy ? 'ring-2 ring-tinta-500 dark:ring-tinta-400' : '' }}">
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