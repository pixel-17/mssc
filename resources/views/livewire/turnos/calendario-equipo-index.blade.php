<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
                Calendario de turnos — mi equipo
            </h2>
        </div>

        <p class="text-sm text-gray-500">
            @if ($esJefeDeArea)
                Tus trabajadores directos y los jefes de las sub-unidades de tu área.
            @else
                Solo los trabajadores de los que eres jefe inmediato (automático o adicional).
            @endif
            Los de régimen 728 se pintan directo en la grilla (M/T/N/D); los de horario ordinario (276) se cargan en
            "Crear/editar horario".
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
                    if (!this.hayCambios || confirm('Tienes cambios sin guardar. ¿Descartarlos?')) { this.$wire[accion](); }
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

            @if (session('mensaje'))
                <div class="glass-card p-4 text-sm text-green-700 dark:text-green-400">
                    {{ session('mensaje') }}
                </div>
            @endif

            @if ($mensaje)
                <p class="text-sm text-green-700 dark:text-green-400">{{ $mensaje }}</p>
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
                                    {{ $trabajador->nombre_completo }}
                                    <span class="text-xs text-gray-400">({{ $trabajador->regimen }})</span>
                                </td>
                                @if ($trabajador->regimen === '728' && $trabajador->activo)
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
                                    @if ($trabajador->regimen === '728')
                                        <label class="inline-flex items-center gap-1 text-xs text-gray-500 mr-2">
                                            <input type="checkbox" class="rounded border-gray-300" value="{{ $trabajador->id }}" x-model="seleccion">
                                            marcar
                                        </label>
                                    @else
                                        <a href="{{ route('turnos.configuracion', $trabajador) }}" class="text-sm text-tinta-700 dark:text-tinta-300 underline hover:no-underline">
                                            Crear/editar horario
                                        </a>
                                    @endif
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
