@php
    $fmt = fn (?int $m) => $m === null ? '—' : ($m >= 60 ? intdiv($m, 60).' h '.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT).' min' : $m.' min');
@endphp
<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between flex-wrap gap-3 print:hidden">
            <div>
                <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
                    Resumen de papeletas
                </h2>
                <p class="text-sm text-gray-500 dark:text-tinta-50/70">
                    Reporte de cierre de mes: salud del proceso y horas fuera de sede por trabajador.
                </p>
            </div>

            @if ($tab === 'horas')
                <div class="flex items-center gap-2">
                    <button type="button" onclick="window.print()" class="btn-secondary text-xs">
                        Imprimir
                    </button>
                    <button type="button" wire:click="exportar" wire:loading.attr="disabled" class="btn-primary text-xs">
                        <span wire:loading.remove wire:target="exportar">Exportar a Excel</span>
                        <span wire:loading wire:target="exportar">Generando...</span>
                    </button>
                </div>
            @endif
        </div>

        {{-- ============================= PESTAÑAS ============================= --}}
        <div class="flex gap-2 print:hidden">
            <button
                type="button"
                wire:click="cambiarTab('horas')"
                class="px-3 py-1.5 text-sm font-medium rounded-md {{ $tab === 'horas' ? 'bg-tinta-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}"
            >
                Horas y descuentos
            </button>
        </div>

        {{-- ============================= PESTAÑA: RESUMEN ============================= --}}
        @if ($tab === 'resumen' && $puedeVerResumen)
            @php
                $columnas = ['total' => 'Total', 'autorizadas' => 'Autorizadas', 'rechazadas' => 'Rechazadas', 'no_prosperaron' => 'Vencidas / canceladas', 'en_tramite' => 'En trámite'];
                $tablas = ['Por motivo' => $resumenPapeletas['por_motivo'], 'Por unidad orgánica' => $resumenPapeletas['por_unidad']];
            @endphp

            <div class="glass-card p-4">
                <label for="resumen-mes" class="block text-sm font-medium mb-1">Mes</label>
                <input id="resumen-mes" type="month" wire:model.live="mes" class="rounded-md border-gray-300 dark:bg-gray-800">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="glass-card p-4">
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50">Papeletas del mes</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $resumenPapeletas['total'] }}</p>
                </div>
                <div class="glass-card p-4">
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50">Tiempo promedio de los jefes en decidir</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fmt($resumenPapeletas['minutos_jefe']) }}</p>
                </div>
                <div class="glass-card p-4">
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50">Tiempo promedio de RR. HH. en decidir</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fmt($resumenPapeletas['minutos_rrhh']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50">Desde que el jefe resuelve hasta que RR. HH. resuelve.</p>
                </div>
            </div>

            @foreach ($tablas as $titulo => $filas)
                <div class="glass-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">{{ $titulo }}</h3>
                    </div>
                    @if ($filas->isEmpty())
                        <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">Sin papeletas en este mes.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-tinta-50/70 dark:bg-white/5">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">{{ $titulo === 'Por motivo' ? 'Motivo' : 'Unidad' }}</th>
                                        @foreach ($columnas as $etiqueta)
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">{{ $etiqueta }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($filas as $fila)
                                        <tr class="border-t border-gray-100 dark:border-white/10">
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $fila['nombre'] }}</td>
                                            @foreach (array_keys($columnas) as $clave)
                                                <td class="px-4 py-2 text-sm text-right text-gray-500 dark:text-tinta-100/60">{{ $fila[$clave] }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="glass-card overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Jefes que más tardan en decidir (promedio, top 10)</h3>
                </div>
                @if ($resumenPapeletas['jefes_lentos']->isEmpty())
                    <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">Sin decisiones de jefes en este mes.</p>
                @else
                    <table class="min-w-full">
                        <tbody>
                            @foreach ($resumenPapeletas['jefes_lentos'] as $jefe)
                                <tr class="border-t border-gray-100 dark:border-white/10 first:border-t-0">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $jefe['nombre'] }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-500 dark:text-tinta-100/60">{{ $jefe['papeletas'] }} papeletas</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-500 dark:text-tinta-100/60">{{ $fmt($jefe['minutos']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- ============================= PESTAÑA: HORAS Y DESCUENTOS ============================= --}}
        @if ($tab === 'horas')
            {{-- ============================= FILTROS ============================= --}}
            <div class="glass-card p-4 space-y-4 print:hidden">
                <div class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="horas-acumuladas-index-mes" class="block text-sm font-medium mb-1">Mes</label>
                        <input id="horas-acumuladas-index-mes" type="month" wire:model.live="mes" class="rounded-md border-gray-300 dark:bg-gray-800">
                    </div>

                    <div class="max-w-xs">
                        <label for="horas-acumuladas-index-buscar" class="block text-sm font-medium mb-1">Buscar por nombre o DNI</label>
                        <input id="horas-acumuladas-index-buscar"
                            type="search"
                            wire:model.live.debounce.400ms="buscar"
                            placeholder="Nombre, apellido o DNI..."
                            class="w-full rounded-md border-gray-300 dark:bg-gray-800"
                        >
                    </div>

                    <div class="max-w-xs">
                        <label for="horas-acumuladas-index-sedeId" class="block text-sm font-medium mb-1">Sede</label>
                        <select id="horas-acumuladas-index-sedeId" wire:model.live="sedeId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                            <option value="">Todas</option>
                            @foreach ($sedes as $sede)
                                <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="max-w-xs">
                        <label for="horas-acumuladas-index-motivoId" class="block text-sm font-medium mb-1">Motivo</label>
                        <select id="horas-acumuladas-index-motivoId" wire:model.live="motivoId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                            <option value="">Todos</option>
                            @foreach ($motivos as $motivo)
                                <option value="{{ $motivo->id }}">{{ $motivo->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="max-w-xs">
                        <label for="horas-acumuladas-index-regimen" class="block text-sm font-medium mb-1">Régimen</label>
                        <select id="horas-acumuladas-index-regimen" wire:model.live="regimen" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                            <option value="">Todos</option>
                            <option value="276">276 (día)</option>
                            <option value="728">728 (rotativo)</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pb-2">
                        <input type="checkbox" wire:model.live="soloConDescuento" id="soloConDescuento" class="rounded border-gray-300">
                        <label for="soloConDescuento" class="text-sm">Solo motivos con descuento</label>
                    </div>

                    <button type="button" wire:click="limpiarFiltros" class="btn-row mb-2">
                        <x-icon name="x-mark" class="size-3.5" />
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
                    <p class="text-2xl font-bold text-tinta-950 dark:text-white">{{ $resumen->count() }}</p>
                </div>
                <div class="glass-card p-4">
                    <p class="text-xs uppercase text-gray-500">Papeletas cerradas</p>
                    <p class="text-2xl font-bold text-tinta-950 dark:text-white">{{ $resumen->sum('papeletas') }}</p>
                </div>
                <div class="glass-card p-4">
                    <p class="text-xs uppercase text-gray-500">Horas fuera de sede</p>
                    <p class="text-2xl font-bold text-tinta-950 dark:text-white">{{ $fmtHoras((int) $resumen->sum('minutos_totales')) }}</p>
                </div>
                <div class="glass-card p-4">
                    <p class="text-xs uppercase text-gray-500">Horas con descuento</p>
                    <p class="text-2xl font-bold {{ $resumen->sum('minutos_con_descuento') > 0 ? 'text-red-600' : 'text-gray-500' }}">
                        {{ $fmtHoras((int) $resumen->sum('minutos_con_descuento')) }}
                    </p>
                </div>
                <div class="glass-card p-4 col-span-2 lg:col-span-1">
                    <p class="text-xs uppercase text-gray-500">Salió más</p>
                    @if ($mayor)
                        <p class="text-sm font-bold text-tinta-950 dark:text-white truncate" title="{{ $mayor['trabajador'] }}">{{ $mayor['trabajador'] }}</p>
                        <p class="text-xs text-gray-500">{{ $fmtHoras((int) $mayor['minutos_totales']) }}</p>
                    @else
                        <p class="text-sm text-gray-500">—</p>
                    @endif
                </div>
            </div>

            {{-- ============================= SUB-PESTAÑAS ============================= --}}
            <div class="flex gap-2 print:hidden">
                <button
                    type="button"
                    wire:click="$set('vista', 'resumen')"
                    class="px-3 py-1.5 text-sm font-medium rounded-md {{ $vista === 'resumen' ? 'bg-tinta-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}"
                >
                    Resumen del mes
                </button>
                <button
                    type="button"
                    wire:click="$set('vista', 'diario')"
                    class="px-3 py-1.5 text-sm font-medium rounded-md {{ $vista === 'diario' ? 'bg-tinta-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}"
                >
                    Detalle por día
                </button>
            </div>

            {{-- ============================= RESUMEN MENSUAL ============================= --}}
            @if ($vista === 'resumen')
                <div class="glass-card overflow-x-auto">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">
                            {{ $top > 0 ? "Top {$top} del mes por trabajador" : 'Ranking del mes por trabajador' }} ({{ $top > 0 ? min($top, $resumen->count()) : $resumen->count() }} de {{ $resumen->count() }})
                        </h3>
                    </div>

                    @if ($resumen->isEmpty())
                        <p class="p-4 text-sm text-gray-500">No hay papeletas cerradas con retorno en este mes con los filtros elegidos.</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-tinta-50/70 dark:bg-gray-800">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                    <th scope="col" class="px-4 py-2">#</th>
                                    <th scope="col" class="px-4 py-2">Trabajador</th>
                                    <th scope="col" class="px-4 py-2">Sede</th>
                                    <th scope="col" class="px-4 py-2">Unidad orgánica</th>
                                    <th scope="col" class="px-4 py-2 text-right">Papeletas</th>
                                    <th scope="col" class="px-4 py-2 text-right">Horas totales</th>
                                    <th scope="col" class="px-4 py-2 text-right">Horas con descuento</th>
                                    <th scope="col" class="px-4 py-2 print:hidden">Adjuntos</th>
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
                                            <span class="block text-xs text-gray-500 font-normal">DNI {{ $fila['dni'] ?? '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['sede'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['unidad_organica'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500 text-right">{{ $fila['papeletas'] }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right font-semibold">
                                            {{ intdiv($fila['minutos_totales'], 60) }}h {{ str_pad($fila['minutos_totales'] % 60, 2, '0', STR_PAD_LEFT) }}m
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right {{ $fila['minutos_con_descuento'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                            {{ intdiv($fila['minutos_con_descuento'], 60) }}h {{ str_pad($fila['minutos_con_descuento'] % 60, 2, '0', STR_PAD_LEFT) }}m
                                            <span class="text-xs text-gray-500">({{ $fila['papeletas_con_descuento'] }})</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm print:hidden">
                                            <div class="flex items-center gap-1.5">
                                                <a href="{{ route('reportes.sustentos', ['trabajadorId' => $fila['trabajador_id']]) }}"
                                                   class="inline-flex items-center gap-1 rounded-md border border-tinta-200 px-2.5 py-1 text-xs font-semibold text-tinta-700 hover:bg-tinta-50 dark:border-white/10 dark:text-tinta-300 dark:hover:bg-white/5">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                                    Ver adjuntos
                                                </a>
                                                <a href="{{ route('reportes.trabajador-historial', ['trabajadorId' => $fila['trabajador_id']]) }}"
                                                   title="Ver su ficha completa"
                                                   class="inline-flex items-center gap-1 rounded-md border border-tinta-200 px-2.5 py-1 text-xs font-semibold text-tinta-700 hover:bg-tinta-50 dark:border-white/10 dark:text-tinta-300 dark:hover:bg-white/5">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                                    Ficha
                                                </a>
                                            </div>
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
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">
                            Horas por día y trabajador ({{ $detalleDiario->count() }})
                        </h3>
                    </div>

                    @if ($detalleDiario->isEmpty())
                        <p class="p-4 text-sm text-gray-500">No hay papeletas cerradas con retorno en este mes con los filtros elegidos.</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-tinta-50/70 dark:bg-gray-800">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                    <th scope="col" class="px-4 py-2">Día</th>
                                    <th scope="col" class="px-4 py-2">Trabajador</th>
                                    <th scope="col" class="px-4 py-2">Motivo(s)</th>
                                    <th scope="col" class="px-4 py-2 text-right">Horas del día</th>
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
        @endif
    </div>
</div>