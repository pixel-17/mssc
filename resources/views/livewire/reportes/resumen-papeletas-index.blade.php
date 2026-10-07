@php
    $fmt = fn (?int $m) => $m === null ? '—' : ($m >= 60 ? intdiv($m, 60).' h '.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT).' min' : $m.' min');
    $columnas = ['total' => 'Total', 'autorizadas' => 'Autorizadas', 'rechazadas' => 'Rechazadas', 'no_prosperaron' => 'Vencidas / canceladas', 'en_tramite' => 'En trámite'];
    $tablas = ['Por motivo' => $resumen['por_motivo'], 'Por unidad orgánica' => $resumen['por_unidad']];
@endphp
<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">Resumen de papeletas</h2>
            <p class="text-sm text-gray-500 dark:text-tinta-50/70">Cuántas papeletas hubo en el mes, en qué terminaron y cuánto tardan en decidirse.</p>
        </div>

        <div class="glass-card p-4">
            <label for="resumen-mes" class="block text-sm font-medium mb-1">Mes</label>
            <input id="resumen-mes" type="month" wire:model.live="mes" class="rounded-md border-gray-300 dark:bg-gray-800">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="glass-card p-4">
                <p class="text-xs text-gray-500 dark:text-tinta-100/50">Papeletas del mes</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $resumen['total'] }}</p>
            </div>
            <div class="glass-card p-4">
                <p class="text-xs text-gray-500 dark:text-tinta-100/50">Tiempo promedio de los jefes en decidir</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fmt($resumen['minutos_jefe']) }}</p>
            </div>
            <div class="glass-card p-4">
                <p class="text-xs text-gray-500 dark:text-tinta-100/50">Tiempo promedio de RR. HH. en decidir</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fmt($resumen['minutos_rrhh']) }}</p>
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
            @if ($resumen['jefes_lentos']->isEmpty())
                <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">Sin decisiones de jefes en este mes.</p>
            @else
                <table class="min-w-full">
                    <tbody>
                        @foreach ($resumen['jefes_lentos'] as $jefe)
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
    </div>
</div>
