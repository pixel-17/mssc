<div>
    <div class="max-w-6xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">

        <div class="print:hidden">
            <h2 class="font-bold text-2xl text-ocean-950 dark:text-white leading-tight tracking-tight">
                Historial por trabajador
            </h2>
            <p class="text-sm text-gray-500 dark:text-ocean-50/70">
                Busca a un trabajador para ver todo su historial de papeletas, no solo el del mes.
            </p>
        </div>

        @if (! $trabajador)
            {{-- ============================= BUSCADOR ============================= --}}
            <div class="glass-card p-4 space-y-3">
                <label class="block text-sm font-medium mb-1">Buscar trabajador</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Nombre, apellido o DNI..."
                    class="w-full max-w-md rounded-md border-gray-300 dark:bg-gray-800"
                    autofocus
                >

                @if ($sugerencias->isEmpty())
                    <p class="text-sm text-gray-500">
                        {{ $buscar === '' ? 'Escribe para buscar, o mira la lista de tu equipo.' : 'Sin resultados.' }}
                    </p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800 max-w-md">
                        @foreach ($sugerencias as $s)
                            <li>
                                <button
                                    type="button"
                                    wire:click="elegir({{ $s->id }})"
                                    class="w-full text-left px-3 py-2 hover:bg-ocean-50 dark:hover:bg-gray-800 rounded-md text-sm"
                                >
                                    <span class="font-medium text-gray-900 dark:text-white">{{ trim($s->name.' '.$s->apellido) }}</span>
                                    <span class="text-gray-400 text-xs ml-2">{{ $s->dni }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @else
            {{-- ============================= FICHA DEL TRABAJADOR ============================= --}}
            <div class="flex items-center justify-between flex-wrap gap-2 print:hidden">
                <div>
                    <h3 class="text-lg font-bold text-ocean-950 dark:text-white">{{ $trabajador->nombre_completo }}</h3>
                    <p class="text-sm text-gray-500">
                        DNI {{ $trabajador->dni }} · {{ $trabajador->sede?->nombre ?? 'Sin sede' }} · {{ $trabajador->unidadOrganica?->nombre ?? 'Sin unidad' }} · Régimen {{ $trabajador->regimen }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="window.print()" class="btn-secondary text-xs">Imprimir</button>
                    <button type="button" wire:click="quitar" class="btn-secondary text-xs">← Buscar a otro</button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <x-stat-card label="Papeletas (histórico)" :value="$resumen['papeletas_total']" />
                <x-stat-card
                    label="Horas acumuladas"
                    :value="intdiv($resumen['minutos_totales'], 60).'h '.str_pad($resumen['minutos_totales'] % 60, 2, '0', STR_PAD_LEFT).'m'"
                />
                <x-stat-card
                    label="Horas con descuento"
                    :value="intdiv($resumen['minutos_con_descuento'], 60).'h '.str_pad($resumen['minutos_con_descuento'] % 60, 2, '0', STR_PAD_LEFT).'m'"
                    tono="red"
                />
                <x-stat-card label="Rechazadas" :value="$resumen['rechazadas']" tono="red" />
                <x-stat-card label="Vencidas" :value="$resumen['vencidas']" tono="amber" />
                <x-stat-card label="Emergencias" :value="$resumen['emergencias']" tono="purple" />
            </div>

            <div class="glass-card overflow-x-auto">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80">
                        Historial completo ({{ $historial->count() }})
                    </h4>
                </div>

                @if ($historial->isEmpty())
                    <p class="p-4 text-sm text-gray-500">Este trabajador no tiene papeletas registradas.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-ocean-50/70 dark:bg-gray-800">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-4 py-2">Día</th>
                                <th class="px-4 py-2">Motivo</th>
                                <th class="px-4 py-2">Sede</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2 text-right">Horas</th>
                                <th class="px-4 py-2">Sustento</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($historial as $fila)
                                <tr wire:key="hist-{{ $fila['id'] }}">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['dia']->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        {{ $fila['motivo'] }}
                                        @if ($fila['es_emergencia'])
                                            <span class="text-xs text-red-600 font-semibold">· Emergencia</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['sede'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['estado'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right {{ $fila['suma_descuento'] && $fila['minutos'] ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                        @if ($fila['minutos'] !== null)
                                            {{ intdiv($fila['minutos'], 60) }}h {{ str_pad($fila['minutos'] % 60, 2, '0', STR_PAD_LEFT) }}m
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $fila['sustento_estado'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
</div>
