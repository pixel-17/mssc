<div
    x-data="{ todos: null, arrastrando: null, sobre: null }"
    class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6"
>
    {{-- Conectores del árbol. Van aquí (no en app.css) para que funcionen sin recompilar. --}}
    <style>
        .org-tree { --org-line: #cbd5e1; }
        .dark .org-tree { --org-line: rgba(255, 255, 255, .18); }
        .org-tree ul { list-style: none; margin: 0 0 0 .9rem; padding: 0; }
        .org-tree ul > li { position: relative; padding-left: 1.6rem; padding-top: .75rem; }
        .org-tree ul > li::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; border-left: 2px solid var(--org-line); }
        .org-tree ul > li:last-child::before { bottom: auto; height: 2.1rem; }
        .org-tree ul > li::after { content: ''; position: absolute; left: 0; top: 2.1rem; width: 1.3rem; border-top: 2px solid var(--org-line); }
        .org-editando .org-persona { outline: 1px dashed #f59e0b; outline-offset: 1px; }
        .org-tree > ul { margin-left: 0; }
        .org-tree > ul > li { padding-left: 0; }
        .org-tree > ul > li::before, .org-tree > ul > li::after { display: none; }
    </style>

    <x-admin.encabezado titulo="Organigrama del área">
        <div class="flex items-center gap-2">
            <button type="button"
                    wire:click="alternarEdicion"
                    aria-pressed="{{ $modoEdicion ? 'true' : 'false' }}"
                    @class([
                        'text-xs',
                        'btn-primary' => $modoEdicion,
                        'btn-secondary' => ! $modoEdicion,
                    ])>
                {{ $modoEdicion ? 'Salir del modo edición' : 'Modo edición' }}
            </button>
            <button type="button" class="btn-secondary text-xs" @click="todos = true; $dispatch('org-expandir')">Expandir todo</button>
            <button type="button" class="btn-secondary text-xs" @click="todos = false; $dispatch('org-contraer')">Contraer todo</button>
        </div>
    </x-admin.encabezado>

    @if ($modoEdicion)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200" role="status">
            <strong>Modo edición activo.</strong> Arrastra a un trabajador sobre otra unidad; antes de mover nada se te mostrará lo que cambia y tendrás que confirmar. Sal del modo cuando termines para no mover a nadie por accidente.
        </div>
    @endif

    @if ($mensajeOk)
        <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200" role="status">{{ $mensajeOk }}</div>
    @endif

    @if ($mensajeError)
        <div class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200" role="alert">{{ $mensajeError }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-stat-card label="Unidades" :value="$stats['unidades']" />
        <x-stat-card label="Personas" :value="$stats['personas']" />
        <x-stat-card label="Sin sede" :value="$stats['sin_sede']" :hint="$stats['sin_sede'] ? 'Hay que asignarles sede' : 'Todos tienen sede'" />
        <x-stat-card label="Turnos sin jefe" :value="$stats['turnos_sin_jefe']" hint="Unidades 728" />
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <x-admin.filtro-campo label="Buscar" for="organigrama-buscar">
            <x-input id="organigrama-buscar" type="text" wire:model.live.debounce.400ms="buscar" placeholder="Unidad, jefe, trabajador o DNI" />
        </x-admin.filtro-campo>

        <x-admin.filtro-campo label="Sede" for="organigrama-sede">
            <x-select id="organigrama-sede" wire:model.live="sede">
                <option value="">Todas las sedes ({{ array_sum($conteoSedes) }})</option>
                @foreach ($sedes as $s)
                    <option value="{{ $s->id }}">{{ $s->nombre }} ({{ $conteoSedes[(string) $s->id] ?? 0 }})</option>
                @endforeach
                <option value="sin">Sin sede ({{ $conteoSedes['sin'] ?? 0 }})</option>
            </x-select>
        </x-admin.filtro-campo>

        @if ($sede !== '')
            <button type="button" wire:click="limpiarSede" class="pb-2 text-xs text-tinta-700 underline hover:no-underline dark:text-tinta-200">Quitar filtro de sede</button>
        @endif

        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-tinta-50/80 pb-2">
            <input type="checkbox" wire:model.live="verInactivos" class="rounded border-gray-300 text-tinta-600 focus:ring-tinta-500">
            Mostrar desactivados
        </label>

        <div class="ms-auto flex flex-wrap items-center gap-3 pb-2 text-xs text-gray-600 dark:text-tinta-50/70">
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-tinta-600"></span> Jefe</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-emerald-500"></span> Trabajador</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-amber-500"></span> Sede distinta a la de su jefe</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-red-500"></span> Sin sede</span>
        </div>
    </div>

    @if ($raices->isEmpty())
        <div class="glass-card p-8 text-center text-sm text-gray-500 dark:text-tinta-50/70">
            {{ $buscar !== '' || $sede !== '' ? 'Nada coincide con los filtros.' : 'Todavía no hay unidades orgánicas para mostrar.' }}
        </div>
    @else
        <div @class(['org-tree overflow-x-auto pb-4', 'org-editando' => $modoEdicion])>
            <ul>
                @foreach ($raices as $nodo)
                    @include('livewire.organigrama._nodo', ['nodo' => $nodo, 'etiquetasTurno' => $etiquetasTurno, 'forzarAbierto' => $buscar !== '' || $sede !== ''])
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Historial de movimientos --}}
    @if ($historial->isNotEmpty())
        <div class="glass-card p-4">
            <h2 class="text-sm font-semibold text-tinta-950 dark:text-white">Movimientos recientes</h2>
            <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($historial as $fila)
                    @php($h = $fila['movimiento'])
                    <li wire:key="org-mov-{{ $h->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm">
                        <span class="font-medium text-tinta-950 dark:text-white">{{ $h->trabajador?->nombre_completo ?? 'Persona eliminada' }}</span>
                        <span class="text-gray-700 dark:text-tinta-50/80">{{ $h->unidadAnterior?->nombre ?? '—' }} → {{ $h->unidadNueva?->nombre ?? '—' }}</span>
                        @if ($h->esReversion())
                            <span class="rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100">Reversión</span>
                        @endif
                        @if ($h->cambioSede())
                            <span class="rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100">Sede: {{ $h->sedeAnterior?->nombre ?? 'Sin sede' }} → {{ $h->sedeNueva?->nombre ?? 'Sin sede' }}</span>
                        @endif
                        @if (! empty($h->jefes_adicionales_quitados))
                            <span class="rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100">Quitó {{ count($h->jefes_adicionales_quitados) }} {{ count($h->jefes_adicionales_quitados) === 1 ? 'jefe adicional' : 'jefes adicionales' }}</span>
                        @endif
                        @if ($h->deshecho_at)
                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] text-gray-700 dark:bg-white/10 dark:text-tinta-50/80">Deshecho</span>
                        @endif
                        <span class="ms-auto text-xs text-gray-500 dark:text-tinta-50/60">{{ $h->actor?->nombre_completo ?? 'Sistema' }} · {{ $h->created_at->format('d/m/Y H:i') }}</span>
                        @if ($fila['puede_deshacer'])
                            <button type="button" wire:click="deshacerMovimiento({{ $h->id }})" wire:confirm="¿Deshacer este movimiento?" class="btn-secondary text-xs">Deshacer</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Confirmación del movimiento propuesto --}}
    @if ($movimiento)
        @php($t = $movimiento['trabajador'])
        <div class="fixed inset-0 z-50" @keydown.escape.window="$wire.cancelarMovimiento()">
            <div class="absolute inset-0 bg-black/40" wire:click="cancelarMovimiento" aria-hidden="true"></div>

            <div class="absolute left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
                 role="dialog" aria-modal="true" aria-label="Confirmar movimiento">
                <h2 class="text-lg font-semibold text-tinta-950 dark:text-white">Confirmar movimiento</h2>
                <p class="mt-2 text-sm text-gray-700 dark:text-tinta-50/80">
                    Vas a mover a <strong>{{ $t->nombre_completo }}</strong> de
                    <strong>{{ $movimiento['origen']->nombre }}</strong> a
                    <strong>{{ $movimiento['destino']->nombre }}</strong>.
                </p>

                <dl class="mt-4 space-y-3 text-sm">
                    @foreach ([['Jefe inmediato', $movimiento['jefe_inmediato']], ['Jefe de área', $movimiento['jefe_area']]] as [$etiqueta, $fila])
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">{{ $etiqueta }}</dt>
                            <dd class="mt-0.5 text-tinta-950 dark:text-white">
                                @if ($fila['cambia'])
                                    {{ $fila['antes']?->nombre_completo ?? 'Sin asignar' }} → <strong>{{ $fila['despues']?->nombre_completo ?? 'Sin asignar' }}</strong>
                                @else
                                    {{ $fila['despues']?->nombre_completo ?? 'Sin asignar' }} <span class="text-xs text-gray-500 dark:text-tinta-50/60">(no cambia)</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Sede</dt>
                        <dd class="mt-0.5 text-tinta-950 dark:text-white">
                            @if ($movimiento['sede']['propone_cambio'])
                                <label class="flex items-start gap-2">
                                    <input type="checkbox" wire:model="propuesta.cambiar_sede" class="mt-0.5 rounded border-gray-300 text-tinta-600 focus:ring-tinta-500">
                                    <span>
                                        Cambiar la sede de <strong>{{ $movimiento['sede']['actual']?->nombre ?? 'Sin sede' }}</strong>
                                        a <strong>{{ $movimiento['sede']['propuesta']->nombre }}</strong>
                                        <span class="block text-xs text-gray-500 dark:text-tinta-50/60">Es la sede del jefe de {{ $movimiento['destino']->nombre }}. Desmárcalo para conservar la actual.</span>
                                    </span>
                                </label>
                            @else
                                {{ $movimiento['sede']['actual']?->nombre ?? 'Sin sede' }} <span class="text-xs text-gray-500 dark:text-tinta-50/60">(no cambia)</span>
                            @endif
                        </dd>
                    </div>
                    @if ($movimiento['adicionales']->isNotEmpty())
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Jefes inmediatos adicionales</dt>
                            <dd class="mt-0.5 text-tinta-950 dark:text-white">
                                {{ $movimiento['adicionales']->map->nombre_completo->implode(', ') }}
                                <label class="mt-1 flex items-start gap-2">
                                    <input type="checkbox" wire:model="propuesta.quitar_adicionales" class="mt-0.5 rounded border-gray-300 text-tinta-600 focus:ring-tinta-500">
                                    <span>Quitarlos al mover <span class="block text-xs text-gray-500 dark:text-tinta-50/60">Si no lo marcas, los conserva.</span></span>
                                </label>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($movimiento['avisos'] !== [] || $movimiento['pendientes'] > 0)
                    <ul class="mt-4 space-y-1 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        @foreach ($movimiento['avisos'] as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                        @if ($movimiento['pendientes'] > 0)
                            <li>Tiene {{ $movimiento['pendientes'] }} {{ $movimiento['pendientes'] === 1 ? 'papeleta pendiente' : 'papeletas pendientes' }}.</li>
                        @endif
                    </ul>
                @endif

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelarMovimiento" class="btn-secondary text-sm">Cancelar</button>
                    <button type="button" wire:click="confirmarMovimiento" wire:loading.attr="disabled" class="btn-primary text-sm">Confirmar movimiento</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Panel lateral: ficha de la persona --}}
    @if ($ficha)
        @php($p = $ficha['persona'])
        <div class="fixed inset-0 z-40" @keydown.escape.window="$wire.cerrarPersona()">
            <div class="absolute inset-0 bg-black/30" wire:click="cerrarPersona" aria-hidden="true"></div>

            <aside class="absolute right-0 top-0 h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-xl dark:bg-gray-900"
                   role="dialog" aria-modal="true" aria-label="Ficha de {{ $p->nombre_completo }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold text-tinta-950 dark:text-white">{{ $p->nombre_completo }}</h2>
                        <p class="text-sm text-gray-500 dark:text-tinta-50/60">DNI {{ $p->dni ?? '—' }}</p>
                    </div>
                    <button type="button" wire:click="cerrarPersona" class="rounded p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10" aria-label="Cerrar">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @unless ($p->activo)
                    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">Esta persona está desactivada.</p>
                @endunless

                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Unidad</dt>
                        <dd class="mt-0.5 text-tinta-950 dark:text-white">
                            {{ $p->unidadOrganica?->nombre ?? 'Sin unidad' }}
                            @if ($p->unidadOrganica?->padre)
                                <span class="block text-xs text-gray-500 dark:text-tinta-50/60">dentro de {{ $p->unidadOrganica->padre->nombre }}</span>
                            @endif
                            @if ($ficha['encabeza']->isNotEmpty())
                                <span class="block text-xs text-tinta-700 dark:text-tinta-200">Encabeza: {{ $ficha['encabeza']->implode(', ') }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Sede</dt>
                            <dd class="mt-0.5 {{ $p->sede_id === null ? 'font-medium text-red-600' : 'text-tinta-950 dark:text-white' }}">{{ $p->sede?->nombre ?? 'Sin sede' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Régimen</dt>
                            <dd class="mt-0.5 text-tinta-950 dark:text-white">{{ $p->regimen ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Turno</dt>
                            <dd class="mt-0.5 text-tinta-950 dark:text-white">{{ $ficha['turno'] ?? '—' }}</dd>
                        </div>
                    </div>

                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Jefes inmediatos</dt>
                        <dd class="mt-1 space-y-1">
                            @forelse ($ficha['jefes'] as $j)
                                <div class="flex items-center gap-2 text-tinta-950 dark:text-white">
                                    <span>{{ $j['nombre'] }}</span>
                                    @if ($j['adicional'])
                                        <span class="rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100">adicional</span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-amber-700 dark:text-amber-400">Sin jefe inmediato asignado</span>
                            @endforelse
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Jefe de área</dt>
                        <dd class="mt-0.5 text-tinta-950 dark:text-white">{{ $p->jefeArea?->nombre_completo ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Papeletas pendientes</dt>
                        <dd class="mt-1">
                            @if ($ficha['pendientes_total'] === 0)
                                <span class="text-tinta-950 dark:text-white">Ninguna</span>
                            @else
                                <p class="font-medium text-tinta-950 dark:text-white">{{ $ficha['pendientes_total'] }}</p>
                                <ul class="mt-1 space-y-0.5 text-xs text-gray-600 dark:text-tinta-50/70">
                                    @foreach ($ficha['pendientes'] as $fila)
                                        <li>{{ $fila['etiqueta'] }}: {{ $fila['total'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </dd>
                    </div>
                </dl>
            </aside>
        </div>
    @endif
</div>
