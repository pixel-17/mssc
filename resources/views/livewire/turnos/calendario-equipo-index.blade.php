<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
                @if ($vistaAdmin)
                    Calendario de turnos — equipo de {{ $jefe->nombre_completo }}
                @else
                    Calendario de turnos — mi equipo
                @endif
            </h2>
        </div>

        <p class="text-sm text-gray-500">
            @if ($vistaAdmin)
                Lo que ve {{ $jefe->nombre_completo }} como {{ $esJefeDeArea ? 'jefe de área' : 'jefe inmediato' }}. Lo que guardes aquí queda registrado a tu nombre.
            @elseif ($esJefeDeArea)
                Tus trabajadores directos y los jefes de las sub-unidades de tu área.
            @else
                Solo los trabajadores de los que eres jefe inmediato.
            @endif
            Los de régimen 728 se pintan directo en la grilla (M/T/N/D); los de horario ordinario (276) se configuran con
            el botón "Configurar ciclo" de su fila.
        </p>

        {{-- Leyenda: mismo color que las celdas, coherente con "Mi calendario" del Trabajador (Turno::claseColor()). --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['M' => 'Mañana', 'T' => 'Tarde', 'N' => 'Noche', 'D' => 'Descanso', 'DIA' => 'Horario ordinario (276)'] as $sigla => $nombre)
                <span wire:key="calendario-equipo-index-span-{{ $sigla }}" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium
                    {{ \App\Support\TurnoColores::para($sigla) }}">
                    <span class="font-bold">{{ $sigla }}</span> · {{ $nombre }}
                </span>
            @endforeach
        </div>

        {{-- wire:key con $version: al cambiar de mes o guardar, Alpine reinicia la grilla pintable desde la BD. --}}
        <div
            wire:key="calendario-equipo-{{ $version }}"
            @mouseup.window="arrastrando = false"
            x-data="{
                dias: @js($diasIniciales),
                fechas: @js($fechasIso),
                uids728: @js($uids728),
                original: {},
                seleccion: [],
                pincel: 'MANANA',
                arrastrando: false,
                patron: 'M M M M M M D',
                desde: @js($fechasIso[0] ?? ''),
                desfase: 0,
                errorPatron: '',
                claves: { M: 'MANANA', T: 'TARDE', N: 'NOCHE', D: 'DESCANSO' },
                siglas: { MANANA: 'M', TARDE: 'T', NOCHE: 'N', DESCANSO: 'D' },
                clases: @js(\App\Support\TurnoColores::porCodigo()),
                init() { for (const u of this.uids728) { this.original[u] = this.firma(u); } },
                firma(u) { return JSON.stringify(Object.entries(this.dias[u] ?? {}).sort()); },
                get cambios() {
                    const salida = {};
                    for (const u of this.uids728) { if (this.firma(u) !== this.original[u]) { salida[u] = { ...this.dias[u] }; } }
                    return salida;
                },
                get hayCambios() { return Object.keys(this.cambios).length > 0; },
                get objetivo() { return this.uids728.filter(u => !this.seleccion.length || this.seleccion.includes(u)); },
                clase(codigo) { return this.clases[codigo] ?? 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800 text-gray-300'; },
                sigla(codigo) { return this.siglas[codigo] ?? '—'; },
                pintar(u, f) {
                    if (this.pincel === 'BORRAR') { delete this.dias[u][f]; } else { this.dias[u][f] = this.pincel; }
                },
                iniciar(e) {
                    if (!e.target.closest('[data-f]')) { return; }
                    e.preventDefault();
                    this.arrastrando = true;
                    this.celda(e, false);
                },
                celda(e, arrastre) {
                    const c = e.target.closest('[data-f]');
                    if (!c || (arrastre && !this.arrastrando)) { return; }
                    this.pintar(c.dataset.u, c.dataset.f);
                },
                aplicarPatron() {
                    const pasos = this.patron.toUpperCase().split(/[\s,]+/).filter(Boolean).map(t => this.claves[t]);
                    const desfase = parseInt(this.desfase) || 0;
                    if (!pasos.length || pasos.includes(undefined)) {
                        this.errorPatron = 'Usa solo M, T, N o D separados por espacios. Ej.: M M T T N D';
                        return;
                    }
                    this.errorPatron = '';
                    this.objetivo.forEach((u, k) => {
                        let i = 0;
                        for (const f of this.fechas.filter(f => f >= this.desde)) {
                            this.dias[u][f] = pasos[(((i++ - k * desfase) % pasos.length) + pasos.length) % pasos.length];
                        }
                    });
                },
                limpiar() { for (const u of this.objetivo) { this.dias[u] = {}; } },
                navegar(accion) {
                    if (!this.hayCambios) { this.$wire[accion](); return; } mssConfirmar('Tienes cambios sin guardar. ¿Descartarlos?', { aceptar: 'Descartar', peligro: true }).then(ok => { if (ok) { this.$wire[accion](); } });
                }
            }"
            class="space-y-6"
        >
            {{-- Navegación de mes --}}
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" @click="navegar('mesAnterior')" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" aria-label="Mes anterior">&larr; Anterior</button>
                    <button type="button" @click="navegar('irAHoy')" class="px-3 py-1.5 rounded-md border text-sm font-semibold text-tinta-700 dark:text-tinta-300 hover:bg-tinta-50 dark:hover:bg-tinta-500/10 transition-colors">Hoy</button>
                    <span class="text-sm font-semibold w-32 text-center capitalize">{{ $inicioMes->translatedFormat('F Y') }}</span>
                    <button type="button" @click="navegar('mesSiguiente')" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" aria-label="Mes siguiente">Siguiente &rarr;</button>
                </div>
                <span x-show="hayCambios" x-cloak class="text-xs font-medium text-amber-700 dark:text-amber-300">
                    Cambios sin guardar en <span x-text="Object.keys(cambios).length"></span> trabajador(es) 728
                </span>
            </div>

            @if (count($uids728) > 0)
                {{-- Pincel y patrón: solo aplica a las filas 728 de la grilla de abajo. --}}
                <div class="glass-card p-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium mr-1">Turno a pintar (728):</span>
                        @foreach ([
                            'MANANA' => ['M', 'Mañana'],
                            'TARDE' => ['T', 'Tarde'],
                            'NOCHE' => ['N', 'Noche'],
                            'DESCANSO' => ['D', 'Descanso'],
                            'BORRAR' => ['✕', 'Sin programar'],
                        ] as $codigo => [$sigla, $nombre])
                            <button
                                type="button"
                                @click="pincel = '{{ $codigo }}'"
                                :class="[pincel === '{{ $codigo }}' ? 'ring-2 ring-tinta-500 dark:ring-tinta-400 shadow' : 'opacity-80 hover:opacity-100', '{{ $codigo }}' === 'BORRAR' ? 'bg-white dark:bg-gray-900 text-gray-500 border-gray-300 dark:border-gray-700' : clase('{{ $codigo }}')]"
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            >
                                <span class="font-bold">{{ $sigla }}</span> · {{ $nombre }}
                                @isset($horas[$codigo])
                                    <span class="opacity-70">({{ $horas[$codigo] }})</span>
                                @endisset
                            </button>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                        <div>
                            <label for="calendario-equipo-patron" class="block text-xs font-medium mb-1">Repetir patrón</label>
                            <input id="calendario-equipo-patron" type="text" x-model="patron" class="w-52 rounded-md border-gray-300 dark:bg-gray-800 text-sm" placeholder="M M T T N D">
                        </div>
                        <div>
                            <label for="calendario-equipo-desde" class="block text-xs font-medium mb-1">desde el</label>
                            <input id="calendario-equipo-desde" type="date" x-model="desde" :min="fechas[0]" :max="fechas[fechas.length - 1]" class="rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                        </div>
                        <div>
                            <label for="calendario-equipo-desfase" class="block text-xs font-medium mb-1" title="Cada trabajador de la lista arranca el patrón este número de días después del anterior">Escalonar (días)</label>
                            <input id="calendario-equipo-desfase" type="number" min="-30" max="30" x-model="desfase" class="w-20 rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                        </div>
                        <button type="button" @click="aplicarPatron()" class="px-3 py-2 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                            Aplicar a <span x-text="seleccion.length ? seleccion.length + ' marcado(s)' : 'todos (728)'"></span>
                        </button>
                        <button type="button" @click="limpiar()" class="px-3 py-2 rounded-md border text-sm text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800">Limpiar mes</button>
                    </div>
                    <p class="text-xs text-gray-500">
                        Toca o arrastra sobre las celdas de un trabajador 728 para pintarlas con el turno elegido.
                        Marca filas con la casilla para aplicar el patrón o limpiar solo a esas personas; sin marcar, aplica a todas las 728.
                    </p>
                    <p x-show="errorPatron" x-text="errorPatron" x-cloak class="text-sm text-red-600"></p>
                </div>
            @endif

            @if ($mensaje)
                <x-aviso-toast tipo="success">{{ $mensaje }}</x-aviso-toast>
            @endif

            <div class="glass-card overflow-x-auto">
                <table class="min-w-full border-separate border-spacing-1 text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <th scope="col" class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900">Trabajador</th>
                            @foreach ($fechas as $fecha)
                                <th wire:key="calendario-equipo-th-{{ $loop->index }}" scope="col" class="px-1 py-1 text-center font-medium {{ $fecha->toDateString() === $hoy->toDateString() ? 'text-tinta-700 dark:text-tinta-300 font-bold' : '' }}">
                                    <div>{{ $fecha->day }}</div>
                                </th>
                            @endforeach
                            <th scope="col" class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody
                        @mousedown="iniciar($event)"
                        @mouseover="celda($event, true)"
                        @click.self="celda($event, false)"
                    >
                        @forelse ($trabajadores as $trabajador)
                            <tr wire:key="fila-{{ $trabajador->id }}">
                                <td class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-900 whitespace-nowrap align-middle">
                                    @if (in_array($trabajador->id, $soloLectura, true))
                                        <span class="text-tinta-800 dark:text-tinta-200">{{ $trabajador->nombre_completo }}</span>
                                    @else
                                        <a href="{{ route('turnos.calendario.individual-de', $trabajador) }}" class="text-tinta-800 dark:text-tinta-200 hover:underline" title="Ver calendario de {{ $trabajador->nombre_completo }}">
                                            {{ $trabajador->nombre_completo }}
                                        </a>
                                    @endif
                                    <span class="text-xs text-gray-500">({{ $trabajador->regimen }})</span>
                                    @if ($trabajador->regimen === '276' && ! in_array($trabajador->id, $soloLectura, true))
                                        @php($cfg276 = $configs276->get($trabajador->id))
                                        <div class="mt-0.5 flex items-center gap-1.5 text-[11px] {{ $cfg276 ? 'text-gray-600 dark:text-gray-300' : 'text-amber-700 dark:text-amber-300' }}">
                                            @if ($cfg276)
                                                <span>{{ $cfg276->dias_trabajo }}×{{ $cfg276->dias_descanso }} · desde {{ $cfg276->fecha_ancla->format('d/m') }}</span>
                                            @else
                                                <span>Sin ciclo</span>
                                            @endif
                                            <button type="button" wire:click="abrirCiclo({{ $trabajador->id }})" title="{{ $cfg276 ? 'Editar ciclo' : 'Configurar ciclo' }}" aria-label="{{ $cfg276 ? 'Editar ciclo' : 'Configurar ciclo' }} de {{ $trabajador->nombre_completo }}" class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-tinta-600/40 text-tinta-700 dark:text-tinta-300 hover:bg-tinta-50 dark:hover:bg-tinta-500/10 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                                            </button>
                                        </div>
                                    @endif
                                    @if (in_array($trabajador->id, $idsJefes, true))
                                        <span class="ml-1 inline-flex items-center rounded bg-tinta-100 dark:bg-tinta-800 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-tinta-800 dark:text-tinta-200">Jefe</span>
                                    @endif
                                </td>
                                @if (in_array((string) $trabajador->id, $uids728, true))
                                    @foreach ($fechas as $fecha)
                                        @php($f = $fecha->toDateString())
                                        <td class="p-0">
                                            <button
                                                type="button"
                                                data-u="{{ $trabajador->id }}"
                                                data-f="{{ $f }}"
                                                :class="clase(dias['{{ $trabajador->id }}']['{{ $f }}'])"
                                                class="w-9 h-9 flex items-center justify-center rounded-md border text-xs font-semibold select-none cursor-pointer transition-colors {{ $f === $hoy->toDateString() ? 'ring-2 ring-tinta-500 dark:ring-tinta-400' : '' }}"
                                                aria-label="{{ $trabajador->nombre_completo }}, {{ $fecha->translatedFormat('l j') }}"
                                            ><span x-text="sigla(dias['{{ $trabajador->id }}']['{{ $f }}'])"></span></button>
                                        </td>
                                    @endforeach
                                @else
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
                                                    {{ $esHoyCol ? 'ring-2 ring-tinta-500 dark:ring-tinta-400' : '' }}"
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
                                @endif
                                <td class="px-3 py-2 text-right whitespace-nowrap align-middle">
                                    @if (in_array($trabajador->id, $soloLectura, true))
                                        <span class="text-xs text-gray-500">Solo lectura</span>
                                    @elseif ($trabajador->regimen === '728')
                                        <label class="inline-flex items-center gap-1 text-xs text-gray-500 mr-2">
                                            <input type="checkbox" class="rounded border-gray-300" value="{{ $trabajador->id }}" x-model="seleccion">
                                            marcar
                                        </label>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ $vistaAdmin ? 'Este jefe todavía no tiene trabajadores a cargo.' : 'Todavía no tienes trabajadores a cargo.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Modal: ciclo de trabajo para trabajadores 276 --}}
            @if ($cicloUserId !== null)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="ciclo-modal-{{ $cicloUserId }}">
                    <div class="glass-card w-full max-w-md p-6 space-y-4" role="dialog" aria-modal="true" aria-labelledby="ciclo-modal-titulo">
                        <h3 id="ciclo-modal-titulo" class="font-semibold text-lg text-tinta-950 dark:text-tinta-100">Ciclo de trabajo — {{ $cicloNombre }}</h3>
                        <p class="text-xs text-gray-500">
                            Régimen 276: horario ordinario Día. Aquí defines el ciclo de trabajo y descanso. Al guardar se regenera el mes de la fecha de inicio.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-3">
                                <label for="ciclo-fecha" class="block text-xs font-medium mb-1">Fecha de inicio del próximo bloque</label>
                                <input id="ciclo-fecha" type="date" wire:model="cicloFechaAncla" class="w-full rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                                @error('cicloFechaAncla') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="ciclo-trabajo" class="block text-xs font-medium mb-1">Días de trabajo</label>
                                <input id="ciclo-trabajo" type="number" min="1" max="30" wire:model="cicloDiasTrabajo" class="w-full rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                                @error('cicloDiasTrabajo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="ciclo-descanso" class="block text-xs font-medium mb-1">Días de descanso</label>
                                <input id="ciclo-descanso" type="number" min="1" max="30" wire:model="cicloDiasDescanso" class="w-full rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                                @error('cicloDiasDescanso') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" wire:click="cerrarCiclo" class="text-sm text-gray-600 dark:text-gray-300">Cancelar</button>
                            <button type="button" wire:click="guardarCiclo" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-tinta-700 text-white rounded-md text-sm font-semibold hover:bg-tinta-800">
                                Guardar ciclo
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @error('dias') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- Advertencias no bloqueantes: hay que confirmar para guardar --}}
            @if ($advertencias)
                <div class="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 p-4 space-y-3">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Revisa antes de guardar:</p>
                    <ul class="list-disc pl-5 text-sm text-amber-800 dark:text-amber-200 space-y-1">
                        @foreach ($advertencias as $advertencia)
                            <li wire:key="calendario-equipo-li-{{ $loop->index }}">{{ $advertencia }}</li>
                        @endforeach
                    </ul>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="$wire.guardarIgualmente(cambios)" class="inline-flex items-center px-3 py-1.5 bg-amber-600 text-white rounded-md text-sm">Guardar igualmente</button>
                        <button type="button" wire:click="descartarAdvertencias" class="text-sm text-gray-600 dark:text-gray-300">Seguir editando</button>
                    </div>
                </div>
            @endif

            @if (count($uids728) > 0)
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="$wire.guardar(cambios)"
                        x-show="hayCambios"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-tinta-800 text-white rounded-md text-sm disabled:opacity-50"
                    >
                        Guardar turnos del mes (728)
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>