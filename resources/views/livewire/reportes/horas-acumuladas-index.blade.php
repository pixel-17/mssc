<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between flex-wrap gap-3 print:hidden">
            <div>
                <h2 class="font-bold text-2xl text-ocean-950 dark:text-white leading-tight tracking-tight">
                    Horas acumuladas
                </h2>
                <p class="text-sm text-gray-500 dark:text-ocean-50/70">
                    Reporte de cierre de mes: tiempo fuera de sede por trabajador, para revisión de descuentos.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="btn-secondary text-xs">
                    Imprimir
                </button>
                <button type="button" wire:click="exportar" wire:loading.attr="disabled" class="btn-primary text-xs">
                    <span wire:loading.remove wire:target="exportar">Exportar a Excel</span>
                    <span wire:loading wire:target="exportar">Generando...</span>
                </button>
            </div>
        </div>

        {{-- ============================= FILTROS ============================= --}}
        <div class="glass-card p-4 space-y-4 print:hidden">
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium mb-1">Mes</label>
                    <input type="month" wire:model.live="mes" class="rounded-md border-gray-300 dark:bg-gray-800">
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Buscar por nombre o DNI</label>
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="buscar"
                        placeholder="Nombre, apellido o DNI..."
                        class="w-full rounded-md border-gray-300 dark:bg-gray-800"
                    >
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Trabajador</label>
                    <select wire:model.live="trabajadorId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">Todos</option>
                        @foreach ($trabajadoresDisponibles as $trabajador)
                            <option value="{{ $trabajador->id }}">{{ trim($trabajador->name.' '.$trabajador->apellido) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Sede</label>
                    <select wire:model.live="sedeId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">Todas</option>
                        @foreach ($sedes as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Unidad orgánica</label>
                    <select wire:model.live="unidadOrganicaId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">Todas</option>
                        @foreach ($unidadesOrganicas as $unidad)
                            <option value="{{ $unidad->id }}">{{ $unidad->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Motivo</label>
                    <select wire:model.live="motivoId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">Todos</option>
                        @foreach ($motivos as $motivo)
                            <option value="{{ $motivo->id }}">{{ $motivo->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Régimen</label>
                    <select wire:model.live="regimen" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">Todos</option>
                        <option value="276">276 (día)</option>
                        <option value="728">728 (rotativo)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Mostrar en ranking</label>
                    <select wire:model.live="top" class="rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="0">Todos</option>
                        <option value="5">Top 5</option>
                        <option value="10">Top 10</option>
                        <option value="20">Top 20</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 pb-2">
                    <input type="checkbox" wire:model.live="soloConDescuento" id="soloConDescuento" class="rounded border-gray-300">
                    <label for="soloConDescuento" class="text-sm">Solo motivos con descuento</label>
                </div>

                <button type="button" wire:click="limpiarFiltros" class="text-xs text-ocean-600 hover:text-ocean-900 underline pb-2">
                    Limpiar filtros
                </button>
            </div>
        </div>

        {{-- ============================= RESUMEN EN TARJETAS ============================= --}}
        @php
            $fmtHoras = fn (int $min) => intdiv($min, 60).'h '.str_pad($min % 60, 2, '0', STR_PAD_LEFT).'m';
            $mayor = $resumen->first();
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="glass-card p-4">
                <p class="text-xs uppercase text-gray-500">Trabajadores</p>
                <p class="text-2xl font-bold text-ocean-950 dark:text-white">{{ $resumen->count() }}</p>
            </div>
            <div class="glass-card p-4">
                <p class="text-xs uppercase text-gray-500">Papeletas cerradas</p>
                <p class="text-2xl font-bold text-ocean-950 dark:text-white">{{ $resumen->sum('papeletas') }}</p>
            </div>
            <div class="glass-card p-4">
                <p class="text-xs uppercase text-gray-500">Horas fuera de sede</p>
                <p class="text-2xl font-bold text-ocean-950 dark:text-white">{{ $fmtHoras((int) $resumen->sum('minutos_totales')) }}</p>
            </div>
            <div class="glass-card p-4">
                <p class="text-xs uppercase text-gray-500">Horas con descuento</p>
                <p class="text-2xl font-bold {{ $resumen->sum('minutos_con_descuento') > 0 ? 'text-red-600' : 'text-gray-400' }}">
                    {{ $fmtHoras((int) $resumen->sum('minutos_con_descuento')) }}
                </p>
            </div>
            <div class="glass-card p-4 col-span-2 lg:col-span-1">
                <p class="text-xs uppercase text-gray-500">Salió más</p>
                @if ($mayor)
                    <p class="text-sm font-bold text-ocean-950 dark:text-white truncate" title="{{ $mayor['trabajador'] }}">{{ $mayor['trabajador'] }}</p>
                    <p class="text-xs text-gray-500">{{ $fmtHoras((int) $mayor['minutos_totales']) }}</p>
                @else
                    <p class="text-sm text-gray-400">—</p>
                @endif
            </div>
        </div>

        {{-- ============================= TABS ============================= --}}
        <div class="flex gap-2 print:hidden">
            <button
                type="button"
                wire:click="$set('vista', 'resumen')"
                class="px-3 py-1.5 text-sm font-medium rounded-md {{ $vista === 'resumen' ? 'bg-ocean-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}"
            >
                Resumen del mes
            </button>
            <button
                type="button"
                wire:click="$set('vista', 'diario')"
                class="px-3 py-1.5 text-sm font-medium rounded-md {{ $vista === 'diario' ? 'bg-ocean-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}"
            >
                Detalle por día
            </button>
        </div>

        {{-- ============================= RESUMEN MENSUAL ============================= --}}
        @if ($vista === 'resumen')
            <div class="glass-card overflow-x-auto">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80">
                        {{ $top > 0 ? "Top {$top} del mes por trabajador" : 'Ranking del mes por trabajador' }} ({{ $top > 0 ? min($top, $resumen->count()) : $resumen->count() }} de {{ $resumen->count() }})
                    </h3>
                </div>

                @if ($resumen->isEmpty())
                    <p class="p-4 text-sm text-gray-500">No hay papeletas cerradas con retorno en este mes con los filtros elegidos.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-ocean-50/70 dark:bg-gray-800">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-4 py-2">#</th>
                                <th class="px-4 py-2">Trabajador</th>
                                <th class="px-4 py-2">Sede</th>
                                <th class="px-4 py-2">Unidad orgánica</th>
                                <th class="px-4 py-2 text-right">Papeletas</th>
                                <th class="px-4 py-2 text-right">Horas totales</th>
                                <th class="px-4 py-2 text-right">Horas con descuento</th>
                                <th class="px-4 py-2 print:hidden">Adjuntos</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($top > 0 ? $resumen->take($top) : $resumen as $fila)
                                <tr wire:key="resumen-{{ $fila['trabajador_id'] }}">
                                    <td class="px-4 py-3 text-sm text-gray-500 font-semibold">
                                        {{ ['🥇', '🥈', '🥉'][$loop->index] ?? $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                        {{ $fila['trabajador'] }}
                                        <span class="block text-xs text-gray-400 font-normal">DNI {{ $fila['dni'] ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['sede'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['unidad_organica'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 text-right">{{ $fila['papeletas'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right font-semibold">
                                        {{ intdiv($fila['minutos_totales'], 60) }}h {{ str_pad($fila['minutos_totales'] % 60, 2, '0', STR_PAD_LEFT) }}m
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $fila['minutos_con_descuento'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                        {{ intdiv($fila['minutos_con_descuento'], 60) }}h {{ str_pad($fila['minutos_con_descuento'] % 60, 2, '0', STR_PAD_LEFT) }}m
                                        <span class="text-xs text-gray-400">({{ $fila['papeletas_con_descuento'] }})</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm print:hidden">
                                        <a href="{{ route('reportes.sustentos', ['trabajadorId' => $fila['trabajador_id']]) }}" class="text-ocean-600 hover:text-ocean-900 underline">Ver adjuntos</a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <a href="{{ route('reportes.trabajador-historial', ['trabajadorId' => $fila['trabajador_id']]) }}" class="text-ocean-600 hover:text-ocean-900 underline" title="Ver su ficha completa">Ficha</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- ============================= DETALLE POR DÍA ============================= --}}
        @if ($vista === 'diario')
            <div class="glass-card overflow-x-auto">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80">
                        Horas por día y trabajador ({{ $detalleDiario->count() }})
                    </h3>
                </div>

                @if ($detalleDiario->isEmpty())
                    <p class="p-4 text-sm text-gray-500">No hay papeletas cerradas con retorno en este mes con los filtros elegidos.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-ocean-50/70 dark:bg-gray-800">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-4 py-2">Día</th>
                                <th class="px-4 py-2">Trabajador</th>
                                <th class="px-4 py-2">Motivo(s)</th>
                                <th class="px-4 py-2 text-right">Horas del día</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($detalleDiario as $fila)
                                <tr wire:key="diario-{{ $fila['trabajador_id'] }}-{{ $fila['dia'] }}">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($fila['dia'])->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $fila['trabajador'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['motivos'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">
                                        {{ intdiv($fila['minutos'], 60) }}h {{ str_pad($fila['minutos'] % 60, 2, '0', STR_PAD_LEFT) }}m
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
</div>
