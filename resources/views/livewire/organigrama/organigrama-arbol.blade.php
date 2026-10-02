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
        /* Degradé de UNA sola gama (tinta): cuanto más hondo el nivel, más claro. Borde izquierdo + velo de fondo. */
        .org-nivel { border-left: 5px solid var(--org-tono); background-image: linear-gradient(90deg, var(--org-velo), transparent 55%); }
        .org-n0 { --org-tono: #223437; --org-velo: rgba(34, 52, 55, .16); }
        .org-n1 { --org-tono: #324e52; --org-velo: rgba(50, 78, 82, .13); }
        .org-n2 { --org-tono: #436164; --org-velo: rgba(67, 97, 100, .11); }
        .org-n3 { --org-tono: #647d7d; --org-velo: rgba(100, 125, 125, .10); }
        .org-n4 { --org-tono: #98adab; --org-velo: rgba(152, 173, 171, .12); }
        .org-n5 { --org-tono: #c3d0ce; --org-velo: rgba(195, 208, 206, .16); }
        .dark .org-n0 { --org-tono: #c3d0ce; --org-velo: rgba(195, 208, 206, .16); }
        .dark .org-n1 { --org-tono: #98adab; --org-velo: rgba(152, 173, 171, .13); }
        .dark .org-n2 { --org-tono: #647d7d; --org-velo: rgba(100, 125, 125, .12); }
        .dark .org-n3 { --org-tono: #436164; --org-velo: rgba(67, 97, 100, .14); }
        .dark .org-n4 { --org-tono: #324e52; --org-velo: rgba(50, 78, 82, .16); }
        .dark .org-n5 { --org-tono: #293f43; --org-velo: rgba(41, 63, 67, .18); }
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
            @if ($modoEdicion && $esAdmin)
                <div x-data="{ m: false }" class="relative" @click.outside="m = false" @keydown.escape="m = false">
                    <button type="button" class="btn-primary inline-flex items-center gap-1 text-xs" @click="m = ! m" :aria-expanded="m">
                        <x-icon name="plus-circle" class="size-4" /> Agregar
                    </button>
                    <div x-show="m" x-cloak x-transition.opacity.duration.120ms class="absolute right-0 z-30 mt-1 w-52 rounded-xl border border-gray-200 bg-white p-1 shadow-lg dark:border-white/15 dark:bg-gray-800">
                        <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-tinta-950 hover:bg-tinta-50 dark:text-white dark:hover:bg-white/10"
                                @click="m = false; $dispatch('org-unidad-abrir', { id: null, parentId: null })">
                            <x-icon name="building" class="size-4" /> Unidad orgánica
                        </button>
                        <a href="{{ route('usuarios-admin.crear') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-tinta-950 hover:bg-tinta-50 dark:text-white dark:hover:bg-white/10">
                            <x-icon name="users" class="size-4" /> Trabajador
                        </a>
                        <a href="{{ route('sedes.crear') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-tinta-950 hover:bg-tinta-50 dark:text-white dark:hover:bg-white/10">
                            <x-icon name="map-pin" class="size-4" /> Sede
                        </a>
                    </div>
                </div>
            @endif
            <button type="button" class="btn-secondary text-xs" @click="todos = null; $dispatch('org-areas')">Solo áreas</button>
            <button type="button" class="btn-secondary text-xs" @click="todos = true; $dispatch('org-expandir')">Expandir todo</button>
            <button type="button" class="btn-secondary text-xs" @click="todos = false; $dispatch('org-contraer')">Contraer todo</button>
        </div>
    </x-admin.encabezado>

    @if ($modoEdicion)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200" role="status">
            <span class="inline-block size-2 animate-pulse rounded-full bg-amber-500"></span>
            <strong>Modo edición</strong>
            <span class="text-amber-800/80 dark:text-amber-200/80">
                Arrastra trabajadores a una unidad con jefe inmediato{{ $esAdmin ? '; arrastra un jefe inmediato sobre un área con jefe de área para mover su unidad con su gente' : '' }} (se pide confirmación){{ $esAdmin ? ' · usa los íconos de cada unidad y persona para editar' : '' }}.
                Sin arrastrar (táctil o teclado): abre a la persona con clic en su nombre y usa «Mover a otra unidad».
            </span>
        </div>
    @endif

    {{-- Toast de éxito: aparece al cambiar $mensajeOk y se oculta solo. --}}
    <div x-data="{ show: false, texto: '', t: null,
                   init() {
                       this.$wire.$watch('mensajeOk', v => {
                           if (! v) return;
                           this.texto = v; this.show = true; clearTimeout(this.t);
                           this.t = setTimeout(() => { this.show = false; this.$wire.set('mensajeOk', null, false) }, 4500);
                       });
                   } }"
         class="pointer-events-none fixed inset-x-0 bottom-6 z-[60] flex justify-center px-4">
        <div x-show="show" x-cloak
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex max-w-md items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-sm text-white shadow-xl dark:bg-white dark:text-gray-900" role="status">
            <svg class="size-5 shrink-0 text-emerald-400 dark:text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            <span x-text="texto"></span>
            <button type="button" @click="show = false" class="ms-1 opacity-60 hover:opacity-100" aria-label="Cerrar aviso">✕</button>
        </div>
    </div>

    @if ($mensajeError)
        <div class="flex items-start gap-2 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200" role="alert" x-data="{ v: true }" x-show="v">
            <span class="flex-1">{{ $mensajeError }}</span>
            <button type="button" @click="v = false" class="opacity-60 hover:opacity-100" aria-label="Cerrar aviso">✕</button>
        </div>
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
            <span class="inline-flex items-center gap-1" title="Cada nivel del organigrama tiene un tono más claro que el anterior">
                Nivel
                <span class="inline-flex overflow-hidden rounded">
                    @foreach (['#223437', '#324e52', '#436164', '#647d7d', '#98adab', '#c3d0ce'] as $tono)
                        <span class="inline-block h-2.5 w-3" style="background: {{ $tono }}"></span>
                    @endforeach
                </span>
            </span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-tinta-600"></span> Jefe</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-emerald-500"></span> Trabajador</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-amber-500"></span> Sede distinta a la de su jefe</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-red-500"></span> Sin sede</span>
        </div>
    </div>

    @if ($raices->isEmpty())
        <div class="glass-card p-8 text-center text-sm text-gray-500 dark:text-tinta-50/70">
            {{ $buscar !== '' || $sede !== '' ? 'Nada coincide con los filtros.' : 'Todavía no hay unidades orgánicas para mostrar.' }}
            @if ($esAdmin && $buscar === '' && $sede === '')
                <div class="mt-4">
                    <button type="button" class="btn-primary text-sm" @click="$dispatch('org-unidad-abrir', { id: null, parentId: null })">Crear la primera unidad</button>
                </div>
            @endif
        </div>
    @else
        <div @class(['org-tree overflow-x-auto pb-4', 'org-editando' => $modoEdicion])>
            <ul>
                @foreach ($raices as $nodo)
                    @include('livewire.organigrama._nodo', ['nodo' => $nodo, 'etiquetasTurno' => $etiquetasTurno, 'forzarAbierto' => $buscar !== '' || $sede !== '', 'esAdmin' => $esAdmin, 'modoEdicion' => $modoEdicion, 'nivel' => 0])
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
                 role="dialog" aria-modal="true" aria-label="Confirmar movimiento"
                 x-trap.noscroll="true">
                <h2 class="text-lg font-semibold text-tinta-950 dark:text-white">Confirmar movimiento</h2>
                <p class="mt-2 text-sm text-gray-700 dark:text-tinta-50/80">
                    Vas a mover a <strong>{{ $t->nombre_completo }}</strong> de
                    <strong>{{ $movimiento['origen']->nombre }}</strong> a
                    <strong>{{ $movimiento['destino']->nombre }}</strong>.
                </p>

                @if ($esAdmin)
                    <div class="mt-3 flex flex-wrap gap-2 text-xs" role="group" aria-label="Cómo moverlo">
                        <span class="rounded-full bg-tinta-600 px-3 py-1 font-medium text-white">Como trabajador</span>
                        <button type="button" wire:click="ascenderTrabajador" class="rounded-full border border-gray-300 px-3 py-1 font-medium text-tinta-950 hover:bg-gray-50 dark:border-white/20 dark:text-white dark:hover:bg-white/5">
                            Como jefe inmediato de {{ $movimiento['destino']->nombre }}
                        </button>
                    </div>
                @endif

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

    {{-- Pantalla de carga mientras se confirma cualquier movimiento --}}
    <div wire:loading.flex wire:target="confirmarMovimiento,confirmarMovimientoJefe,deshacerMovimiento"
         class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50" role="status" aria-live="polite">
        <div class="flex items-center gap-3 rounded-2xl bg-white px-6 py-4 text-sm font-medium text-tinta-950 shadow-xl dark:bg-gray-900 dark:text-white">
            <svg class="size-5 animate-spin text-tinta-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round" class="opacity-75"/>
            </svg>
            Moviendo y recalculando jefes…
        </div>
    </div>

    {{-- Movimiento de un jefe (inmediato o de área): como trabajador, como jefe inmediato de la otra unidad, o su unidad entera --}}
    @if ($movimientoJefe)
        @php($mt = $movimientoJefe['trabajador'])
        @php($mj = $movimientoJefe['como_jefe'])
        @php($mu = $movimientoJefe['unidad'])
        @php($base = $mt ?? $mj)
        <div class="fixed inset-0 z-50" @keydown.escape.window="$wire.cancelarMovimiento()">
            <div class="absolute inset-0 bg-black/40" wire:click="cancelarMovimiento" aria-hidden="true"></div>

            <div class="absolute left-1/2 top-1/2 max-h-[90vh] w-full max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
                 role="dialog" aria-modal="true" aria-label="Mover jefe"
                 x-trap.noscroll="true">
                <h2 class="text-lg font-semibold text-tinta-950 dark:text-white">{{ $movimientoJefe['es_jefe'] ? 'Mover a' : 'Hacer jefe inmediato a' }} {{ $movimientoJefe['jefe']->nombre_completo }}</h2>
                <p class="mt-1 text-sm text-gray-700 dark:text-tinta-50/80">Destino: <strong>{{ $movimientoJefe['destino']->nombre }}</strong></p>

                @if ($mensajeError)
                    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-500/10 dark:text-red-200" role="alert">{{ $mensajeError }}</p>
                @endif

                <fieldset class="mt-4 space-y-2 text-sm">
                    <legend class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">¿Cómo quieres moverlo?</legend>

                    @if ($movimientoJefe['es_jefe'])
                    <label class="flex items-start gap-2 {{ $mt ? '' : 'opacity-50' }}">
                        <input type="radio" wire:model.live="modoJefe" value="trabajador" class="mt-1" @disabled(! $mt)>
                        <span><strong>Como trabajador.</strong> Pasa a ser trabajador de {{ $movimientoJefe['destino']->nombre }}; su unidad se queda con su gente y un reemplazo.
                            @unless ($mt)<span class="block text-xs text-gray-500">No disponible: {{ $movimientoJefe['errores']['trabajador'] ?? '' }}</span>@endunless</span>
                    </label>

                    @endif

                    <label class="flex items-start gap-2 {{ $mj ? '' : 'opacity-50' }}">
                        <input type="radio" wire:model.live="modoJefe" value="jefe" class="mt-1" @disabled(! $mj)>
                        <span><strong>Como jefe inmediato de {{ $movimientoJefe['destino']->nombre }}.</strong> {{ $movimientoJefe['es_jefe'] ? 'Pasa a esa oficina como jefe; su unidad se queda con su gente y un reemplazo.' : 'Pasa a encabezar esa oficina.' }} Conserva su sede.
                            @unless ($mj)<span class="block text-xs text-gray-500">No disponible: {{ $movimientoJefe['errores']['como_jefe'] ?? '' }}</span>@endunless</span>
                    </label>

                    @if ($movimientoJefe['es_jefe'])
                    <label class="flex items-start gap-2 {{ $mu ? '' : 'opacity-50' }}">
                        <input type="radio" wire:model.live="modoJefe" value="unidad" class="mt-1" @disabled(! $mu)>
                        <span><strong>La unidad completa.</strong> Se mueve con su jefe y todo su personal.
                            @unless ($mu)<span class="block text-xs text-gray-500">No disponible: {{ $movimientoJefe['errores']['unidad'] ?? '' }}</span>@endunless</span>
                    </label>
                    @endif
                </fieldset>

                @if (in_array($modoJefe, ['trabajador', 'jefe'], true) && ($modoJefe === 'trabajador' ? $mt : $mj))
                    @php($mp = $modoJefe === 'trabajador' ? $mt : $mj)

                    {{-- Reemplazo obligatorio por cada unidad que deja --}}
                    @foreach ($mp['unidades'] as $fila)
                        <div class="mt-4 rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10" wire:key="reemplazo-{{ $fila['unidad']->id }}">
                            <p class="text-tinta-950 dark:text-white">
                                Deja de encabezar <strong>{{ $fila['unidad']->nombre }}</strong>
                                ({{ $fila['es_area'] ? $fila['subunidades'].' sub-unidades · ' : '' }}{{ $fila['personas'] }} {{ $fila['personas'] === 1 ? 'persona activa' : 'personas activas' }}).
                            </p>

                            @if ($fila['requiere_reemplazo'])
                                <label class="mt-2 block text-xs font-semibold text-gray-600 dark:text-tinta-50/70" for="reemplazo-{{ $fila['unidad']->id }}">
                                    {{ $fila['es_area'] ? 'Nuevo jefe de área (obligatorio)' : 'Nuevo jefe inmediato (obligatorio)' }}
                                </label>
                                <select id="reemplazo-{{ $fila['unidad']->id }}" wire:model="reemplazos.{{ $fila['unidad']->id }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-800">
                                    <option value="">Elige a su reemplazo…</option>
                                    @foreach ($fila['candidatos'] as $c)
                                        <option value="{{ $c->id }}">{{ $c->nombre_completo }}</option>
                                    @endforeach
                                </select>
                                @if ($fila['candidatos']->isEmpty())
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Nadie de la unidad puede relevarlo ahora (régimen distinto, o ya encabeza otra unidad). Cámbialo desde la ficha de la unidad.</p>
                                @endif
                            @elseif ($fila['promueve'])
                                <p class="mt-2 text-xs text-gray-600 dark:text-tinta-50/70">Lo releva <strong>{{ $fila['promueve']->nombre_completo }}</strong>, que ya es jefe de turno de la unidad.</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($modoJefe === 'jefe')
                        <div class="mt-4 rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Jefes de {{ $movimientoJefe['destino']->nombre }}</p>
                            <p class="mt-1 text-tinta-950 dark:text-white">
                                {{ $mp['jefes_destino']->isEmpty() ? 'Todavía no tiene jefe: quedará como su jefe inmediato.' : $mp['jefes_destino']->pluck('nombre_completo')->join(', ') }}
                            </p>

                            @if ($mp['requiere_reemplazar_destino'])
                                <label class="mt-2 block text-xs font-semibold text-gray-600 dark:text-tinta-50/70" for="reemplaza-destino">
                                    {{ $mp['jefes_destino']->count() > 1 ? 'No hay lugar libre: ¿a cuál jefe releva? (obligatorio)' : 'Solo admite un jefe: releva a' }}
                                </label>
                                <select id="reemplaza-destino" wire:model="reemplazaDestino" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-800">
                                    @if ($mp['jefes_destino']->count() > 1)<option value="">Elige…</option>@endif
                                    @foreach ($mp['jefes_destino'] as $jd)
                                        <option value="{{ $jd->id }}" @selected($mp['jefes_destino']->count() === 1)>{{ $jd->nombre_completo }}</option>
                                    @endforeach
                                </select>
                            @else
                                <p class="mt-1 text-xs text-gray-600 dark:text-tinta-50/70">Hay lugar libre: se suma como un jefe más de la oficina, sin sacar a nadie.</p>
                            @endif
                        </div>
                    @else
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Como trabajador de {{ $movimientoJefe['destino']->nombre }}</dt>
                                <dd class="mt-0.5 text-tinta-950 dark:text-white">
                                    Su jefe inmediato será <strong>{{ $movimientoJefe['destino']->jefe?->nombre_completo ?? '—' }}</strong>
                                    @if ($mp['sede']['propuesta'])
                                        · sede: {{ $mp['sede']['actual']?->nombre ?? 'sin sede' }} → <strong>{{ $mp['sede']['propuesta']->nombre }}</strong>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if ($mp['necesita_turno'])
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="col-span-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Turno como trabajador (728)</div>
                                <select wire:model="turnoJefe.turno" class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-800">
                                    <option value="">Turno…</option>
                                    @foreach ($mp['turnos_validos'] as $codigo)
                                        <option value="{{ $codigo }}">{{ $etiquetasTurno[$codigo] ?? $codigo }}</option>
                                    @endforeach
                                </select>
                                <input type="date" wire:model="turnoJefe.fecha_ancla" class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-800" aria-label="Inicio del ciclo">
                                <input type="number" min="1" wire:model="turnoJefe.dias_trabajo" class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-800" aria-label="Días de trabajo">
                                <input type="number" min="0" wire:model="turnoJefe.dias_descanso" class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-800" aria-label="Días de descanso">
                            </div>
                        @endif
                    @endif

                    @if ($mp['avisos'] !== [])
                        <ul class="mt-4 space-y-1 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                            @foreach ($mp['avisos'] as $aviso)
                                <li>{{ $aviso }}</li>
                            @endforeach
                        </ul>
                    @endif
                @elseif ($modoJefe === 'unidad' && $mu)
                    <p class="mt-4 text-sm text-gray-700 dark:text-tinta-50/80">
                        <strong>{{ $mu['jefe']->nombre_completo }}</strong> se mueve con su unidad
                        <strong>{{ $mu['unidad']->nombre }}</strong>
                        ({{ $mu['personas'] }} {{ $mu['personas'] === 1 ? 'persona activa' : 'personas activas' }})
                        de <strong>{{ $mu['origen']?->nombre ?? 'raíz' }}</strong> a <strong>{{ $mu['destino']->nombre }}</strong>.
                        Jefe de área de esa gente: {{ $mu['jefe_area']['antes']?->nombre_completo ?? 'Sin asignar' }} →
                        <strong>{{ $mu['jefe_area']['despues']?->nombre_completo ?? 'Sin asignar' }}</strong>.
                    </p>
                    @if ($mu['avisos'] !== [])
                        <ul class="mt-3 space-y-1 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                            @foreach ($mu['avisos'] as $aviso)
                                <li>{{ $aviso }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif

                <p class="mt-3 text-xs text-gray-500 dark:text-tinta-50/60">Las papeletas ya creadas conservan su jefe.</p>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelarMovimiento" class="btn-secondary text-sm">Cancelar</button>
                    <button type="button" wire:click="confirmarMovimientoJefe" wire:loading.attr="disabled" wire:target="confirmarMovimientoJefe"
                            @disabled(! in_array($modoJefe, ['trabajador', 'jefe', 'unidad'], true)) class="btn-primary text-sm disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirmarMovimientoJefe">Confirmar movimiento</span>
                        <span wire:loading wire:target="confirmarMovimientoJefe">Moviendo…</span>
                    </button>
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
                   role="dialog" aria-modal="true" aria-label="Ficha de {{ $p->nombre_completo }}"
                   x-trap.noscroll="true">
                <div class="flex items-start justify-between gap-3">
                    <x-avatar :user="$p" size="size-14" class="text-lg" />
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-lg font-semibold text-tinta-950 dark:text-white">{{ $p->nombre_completo }}</h2>
                        <p class="text-sm text-gray-500 dark:text-tinta-50/60">DNI {{ $p->dni ?? '—' }}</p>
                    </div>
                    @if ($esAdmin)
                        <button type="button" class="btn-secondary shrink-0 text-xs" @click="$dispatch('org-trabajador-abrir', { id: {{ $p->id }} }); $wire.cerrarPersona()">Editar</button>
                    @endif
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

                {{-- Alternativa al arrastre (táctil / teclado): misma confirmación que al soltar sobre una unidad. --}}
                @if ($modoEdicion && $destinosMover !== [])
                    <section class="mt-6 border-t border-gray-100 pt-5 dark:border-white/10">
                        <label for="mover-destino" class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Mover a otra unidad</label>
                        <p class="mt-1 text-xs text-gray-500 dark:text-tinta-50/60">Elige el destino. Antes de mover se te pide confirmación.</p>

                        <div class="mt-2">
                            <x-organigrama.combobox model="destinoMover" id="mover-destino" :options="$destinosMover" :limpiable="false"
                                                    placeholder="Buscar unidad…" vacio="Elige la unidad de destino" />
                        </div>
                        <x-input-error for="destinoMover" class="mt-1" />

                        <button type="button" wire:click="moverDesdeFicha" wire:loading.attr="disabled" wire:target="moverDesdeFicha"
                                class="btn-primary mt-3 w-full text-sm">
                            <span wire:loading.remove wire:target="moverDesdeFicha">Continuar</span>
                            <span wire:loading wire:target="moverDesdeFicha">Revisando…</span>
                        </button>
                    </section>
                @endif
            </aside>
        </div>
    @endif

    @if ($esAdmin)
        <livewire:organigrama.unidad-modal />
        <livewire:organigrama.trabajador-modal />
    @endif
</div>
