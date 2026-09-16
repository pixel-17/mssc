<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Calendario de turnos — mi equipo
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
            @if ($esJefeDeArea)
                Todos los trabajadores de tu área y sus sub-unidades.
            @else
                Solo los trabajadores de los que eres jefe inmediato (automático o adicional).
            @endif
            M = Mañana, T = Tarde, N = Noche, D = Descanso, DIA = horario ordinario (276).
        </p>

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
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900">Trabajador</th>
                        @foreach ($dias as $dia)
                            <th class="px-2 py-2 text-center">{{ $dia }}</th>
                        @endforeach
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($trabajadores as $trabajador)
                        <tr wire:key="fila-{{ $trabajador->id }}">
                            <td class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900 whitespace-nowrap">
                                {{ $trabajador->nombre_completo }}
                                <span class="text-xs text-gray-400">({{ $trabajador->regimen }})</span>
                            </td>
                            @foreach ($dias as $dia)
                                @php($turno = $turnosPorUsuario->get($trabajador->id)?->get($dia))
                                <td class="px-2 py-2 text-center {{ $turno && $turno->es_descanso ? 'text-gray-400' : '' }}">
                                    {{ $turno?->etiqueta() ?? '—' }}
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('turnos.configuracion', $trabajador) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
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
