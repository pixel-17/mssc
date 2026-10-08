<div>
    <div class="max-w-6xl mx-auto py-12 sm:px-6 lg:px-8 space-y-8">
        <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
            Bandeja de RRHH
        </h2>

        {{-- Resumen: cuánto hay en cada bandeja y desde cuándo espera la más antigua. --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="glass-card p-4">
                <p class="text-xs text-gray-500 dark:text-tinta-100/50">Por decidir</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $porDecidir->total() }}</p>
                @if ($masAntigua)
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50">La más antigua espera desde {{ $masAntigua->diffForHumans() }}</p>
                @endif
            </div>
            <div class="glass-card p-4">
                <p class="text-xs text-gray-500 dark:text-tinta-100/50">Revisión post-hoc</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $posthocPendientes->total() }}</p>
            </div>
            <div class="glass-card p-4">
                <p class="text-xs text-gray-500 dark:text-tinta-100/50">Sustentos por revisar</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $sustentosPorRevisar->total() }}</p>
            </div>
            <div class="glass-card p-4">
                <p class="text-xs text-gray-500 dark:text-tinta-100/50">Abandonos en regularización</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $abandonosEnRegularizacion->total() }}</p>
            </div>
        </div>

        {{-- Filtros: se aplican a las tres listas de abajo. --}}
        @php
            $claseCampo = 'w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500';
            $claseEtiqueta = 'block text-sm font-medium mb-1 text-gray-700 dark:text-tinta-50/80';
        @endphp
        <div class="glass-card p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
            <div class="lg:col-span-2">
                <label for="rrhh-index-buscar" class="{{ $claseEtiqueta }}">Buscar trabajador</label>
                <input id="rrhh-index-buscar" type="text" wire:model.live.debounce.300ms="buscar" placeholder="Nombre o apellido..." class="{{ $claseCampo }}">
            </div>
            <div>
                <label for="rrhh-index-motivo" class="{{ $claseEtiqueta }}">Motivo</label>
                <select id="rrhh-index-motivo" wire:model.live="motivoId" class="{{ $claseCampo }}">
                    <option value="">Todos</option>
                    @foreach ($motivos as $motivo)
                        <option value="{{ $motivo->id }}">{{ $motivo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rrhh-index-sede" class="{{ $claseEtiqueta }}">Sede</label>
                <select id="rrhh-index-sede" wire:model.live="sedeId" class="{{ $claseCampo }}">
                    <option value="">Todas</option>
                    @foreach ($sedes as $sede)
                        <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rrhh-index-regimen" class="{{ $claseEtiqueta }}">Régimen</label>
                <select id="rrhh-index-regimen" wire:model.live="regimen" class="{{ $claseCampo }}">
                    <option value="">Todos</option>
                    <option value="276">276</option>
                    <option value="728">728</option>
                </select>
            </div>
            <div>
                <label for="rrhh-index-desde" class="{{ $claseEtiqueta }}">Día desde</label>
                <input id="rrhh-index-desde" type="date" wire:model.live="desde" class="{{ $claseCampo }}">
            </div>
            <div>
                <label for="rrhh-index-hasta" class="{{ $claseEtiqueta }}">Día hasta</label>
                <input id="rrhh-index-hasta" type="date" wire:model.live="hasta" class="{{ $claseCampo }}">
            </div>
            @if ($hayFiltros)
                <div class="sm:col-span-2 lg:col-span-6">
                    <button type="button" wire:click="limpiarFiltros" class="text-xs underline text-gray-500 dark:text-tinta-100/60">Limpiar filtros</button>
                </div>
            @endif
        </div>

        {{-- Por decidir --}}
        <div class="glass-card overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Por decidir ({{ $porDecidir->total() }})</h3>
            </div>
            @if ($porDecidir->isEmpty())
                <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">
                    {{ $hayFiltros ? 'Ninguna papeleta coincide con los filtros.' : 'No hay papeletas pendientes de RRHH.' }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Día</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Creada</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($porDecidir as $papeleta)
                                <tr wire:key="por-decidir-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->dia_operativo->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->created_at->format('d/m/Y H:i') }}<span class="block text-xs">{{ $papeleta->created_at->diffForHumans() }}</span></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2 flex-wrap">
                                            <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium mr-2">Ver</a>
                                            <x-boton-confirmar
                                                :action="route('rrhh.papeletas.aprobar', $papeleta)"
                                                label="Aprobar"
                                                color="green"
                                            />
                                            @unless ($papeleta->sinJefatura())
                                                <x-accion-comentario :action="route('rrhh.papeletas.observar', $papeleta)" label="Observar" color="orange" :sugerencias="config('respuestas_rapidas.rrhh_observar')" />
                                            @endunless
                                            <x-accion-comentario :action="route('rrhh.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" :sugerencias="config('respuestas_rapidas.rrhh_rechazar')" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($porDecidir->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $porDecidir->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            @endif
        </div>

        {{-- Abandonos en regularización (ventana de 48 h hábiles) --}}
        @if ($abandonosEnRegularizacion->isNotEmpty())
            <div class="glass-card overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Abandonos en regularización ({{ $abandonosEnRegularizacion->total() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                @foreach (['Trabajador', 'Motivo', 'Día', 'Plazo', ''] as $columna)
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">{{ $columna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($abandonosEnRegularizacion as $papeleta)
                                @php $vencido = $papeleta->regularizacion_fecha_limite?->isPast(); @endphp
                                <tr wire:key="abandono-regularizacion-{{ $papeleta->id }}" class="border-t border-gray-100 dark:border-white/10">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->dia_operativo->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm {{ $vencido ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-tinta-100/60' }}">
                                        @if ($papeleta->regularizacion_fecha_limite)
                                            {{ $papeleta->regularizacion_fecha_limite->format('d/m/Y H:i') }}
                                            <span class="block text-xs">{{ $vencido ? 'Vencido ' : 'Vence ' }}{{ $papeleta->regularizacion_fecha_limite->diffForHumans() }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 font-medium">Ver</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($abandonosEnRegularizacion->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $abandonosEnRegularizacion->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Revisión post-hoc --}}
        @if ($posthocPendientes->isNotEmpty())
            <div class="glass-card overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Revisión post-hoc — jefe autorizó fuera de horario RRHH ({{ $posthocPendientes->total() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Autorizó</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Estado</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($posthocPendientes as $papeleta)
                                <tr wire:key="posthoc-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->resueltoPorJefe?->nombre_completo ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">
                                        @if ($papeleta->revision_posthoc_estado === 'respondida')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-500/15 text-blue-700 dark:text-blue-300">Jefe respondió</span>
                                        @else
                                            Pendiente
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Revisar →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($posthocPendientes->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $posthocPendientes->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Sustentos por revisar --}}
        @if ($sustentosPorRevisar->isNotEmpty())
            <div class="glass-card overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Sustentos por revisar ({{ $sustentosPorRevisar->total() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Plazo del sustento</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($sustentosPorRevisar as $papeleta)
                                <tr wire:key="sustento-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    @php $limite = $papeleta->sustentos->first()?->fecha_limite; @endphp
                                    <td class="px-4 py-3 text-sm">
                                        @if ($limite)
                                            <span class="{{ $limite->isPast() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-tinta-100/60' }}">
                                                {{ $limite->format('d/m/Y H:i') }}
                                            </span>
                                            <span class="block text-xs {{ $limite->isPast() ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-tinta-100/60' }}">
                                                {{ $limite->isPast() ? 'Venció '.$limite->diffForHumans() : 'Vence '.$limite->diffForHumans() }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Revisar sustento →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($sustentosPorRevisar->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $sustentosPorRevisar->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
