<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Calendario de turnos — mi equipo
            </h2>

            <div class="flex items-center gap-2">
                <button type="button" wire:click="mesAnterior" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    &larr; Anterior
                </button>
                <button type="button" wire:click="irAHoy" class="px-3 py-1.5 rounded-md border text-sm font-semibold text-ocean-700 dark:text-ocean-300 hover:bg-ocean-50 dark:hover:bg-ocean-500/10 transition-colors">
                    Hoy
                </button>
                <span class="text-sm font-semibold w-32 text-center capitalize">
                    {{ $inicioMes->translatedFormat('F Y') }}
                </span>
                <button type="button" wire:click="mesSiguiente" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    Siguiente &rarr;
                </button>
            </div>
        </div>

        <p class="text-sm text-gray-500">
            @if ($esJefeDeArea)
                Todos los trabajadores de tu área y sus sub-unidades.
            @else
                Solo los trabajadores de los que eres jefe inmediato (automático o adicional).
            @endif
            Toca una celda con turno para ver el detalle.
        </p>

        {{-- Leyenda: mismo color que las celdas de la grilla, coherente con
             "Mi calendario" del Trabajador (Turno::claseColor()). --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['M' => 'Mañana', 'T' => 'Tarde', 'N' => 'Noche', 'D' => 'Descanso', 'DIA' => 'Horario ordinario (276)'] as $sigla => $nombre)
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium
                    {{ match ($sigla) {
                        'M' => 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30',
                        'T' => 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/30',
                        'N' => 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:border-indigo-500/30',
                        'D' => 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-500/10 dark:text-gray-400 dark:border-gray-500/20',
                        default => 'bg-sky-100 text-sky-800 border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/30',
                    } }}">
                    <span class="font-bold">{{ $sigla }}</span> · {{ $nombre }}
                </span>
            @endforeach
        </div>

        <div class="glass-card p-4 text-sm flex items-center gap-2">
            <span class="font-semibold">Modo estricto 728:</span>
            @if ($modoEstricto728Activo)
                <span class="text-red-700 dark:text-red-400 font-semibold">Activado</span>
                <span class="text-gray-500">— un 728 sin turno vigente no puede crear papeleta.</span>
            @else
                <span class="text-green-700 dark:text-green-400 font-semibold">Desactivado</span>
                <span class="text-gray-500">— un 728 nunca es bloqueado por falta de turno.</span>
            @endif
            <span class="text-xs text-gray-400 ml-2">(solo lectura aquí; lo cambia Admin en Configuraciones)</span>
        </div>

        @if (session('mensaje'))
            <div class="glass-card p-4 text-sm text-green-700 dark:text-green-400">
                {{ session('mensaje') }}
            </div>
        @endif

        <div class="glass-card overflow-x-auto">
            <table class="min-w-full border-separate border-spacing-1 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900">Trabajador</th>
                        @foreach ($dias as $dia)
                            @php($esHoyCol = $inicioMes->copy()->day($dia)->isSameDay($hoy))
                            <th class="px-1 py-2 text-center {{ $esHoyCol ? 'text-ocean-700 dark:text-ocean-300 font-bold' : '' }}">{{ $dia }}</th>
                        @endforeach
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trabajadores as $trabajador)
                        <tr wire:key="fila-{{ $trabajador->id }}">
                            <td class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900 whitespace-nowrap align-middle">
                                {{ $trabajador->nombre_completo }}
                                <span class="text-xs text-gray-400">({{ $trabajador->regimen }})</span>
                            </td>
                            @foreach ($dias as $dia)
                                @php($turno = $turnosPorUsuario->get($trabajador->id)?->get($dia))
                                @php($esHoyCol = $inicioMes->copy()->day($dia)->isSameDay($hoy))
                                <td class="p-0">
                                    <div
                                        x-data="{ abierto: false }"
                                        @if ($turno) @click="abierto = !abierto" @click.outside="abierto = false" @endif
                                        class="relative w-9 h-9 flex items-center justify-center rounded-md border text-xs font-semibold transition-all
                                            {{ $turno ? $turno->claseColor() : 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800 text-gray-300' }}
                                            {{ $turno ? 'cursor-pointer hover:shadow-md hover:-translate-y-0.5' : '' }}
                                            {{ $esHoyCol ? 'ring-2 ring-ocean-500 dark:ring-ocean-400' : '' }}"
                                    >
                                        {{ $turno?->etiqueta() ?? '—' }}

                                        @if ($turno)
                                            <div
                                                x-show="abierto"
                                                x-cloak
                                                x-transition
                                                class="absolute z-10 top-full right-0 mt-1 w-48 rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-700 shadow-lg p-3 text-left text-xs text-gray-700 dark:text-gray-300"
                                            >
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $trabajador->nombre_completo }}</p>
                                                <p class="mt-1">{{ $turno->nombreTurno() }}</p>
                                                @if (! $turno->es_descanso && $turno->hora_inicio && $turno->hora_fin)
                                                    <p class="mt-1">{{ substr($turno->hora_inicio, 0, 5) }} – {{ substr($turno->hora_fin, 0, 5) }}</p>
                                                @endif
                                                @if ($turno->sede)
                                                    <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $turno->sede->nombre }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-right whitespace-nowrap align-middle">
                                <a href="{{ route('turnos.configuracion', $trabajador) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline hover:no-underline">
                                    Crear/editar horario
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="100" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Todavía no tienes trabajadores a cargo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
