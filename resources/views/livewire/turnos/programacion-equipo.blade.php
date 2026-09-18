<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Programar turnos — mi equipo (728)
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                @if ($esJefeDeArea)
                    Trabajadores 728 de tu área y sus sub-unidades.
                @else
                    Trabajadores 728 de los que eres jefe inmediato.
                @endif
                Elige un turno y haz clic o arrastra sobre las celdas. Al guardar se reemplaza el mes completo
                de cada trabajador que modifiques; los demás no se tocan.
                @if ($sinProgramacionDiaria > 0)
                    <span class="text-gray-400">({{ $sinProgramacionDiaria }} {{ $sinProgramacionDiaria === 1 ? 'persona no aparece' : 'personas no aparecen' }}: régimen 276 o inactivas.)</span>
                @endif
            </p>
        </div>

        {{-- wire:key con $version: al cambiar de mes o guardar, Alpine reinicia la grilla desde la BD. --}}
        <div
            wire:key="programacion-equipo-{{ $version }}"
            @mouseup.window="arrastrando = false"
            x-data="{
                dias: @js($diasIniciales),
                fechas: @js($fechasIso),
                uids: @js($uids),
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
                clases: {
                    MANANA: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30',
                    TARDE: 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/30',
                    NOCHE: 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:border-indigo-500/30',
                    DESCANSO: 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-500/10 dark:text-gray-400 dark:border-gray-500/20'
                },
                init() { for (const u of this.uids) { this.original[u] = this.firma(u); } },
                firma(u) { return JSON.stringify(Object.entries(this.dias[u] ?? {}).sort()); },
                get cambios() {
                    const salida = {};
                    for (const u of this.uids) { if (this.firma(u) !== this.original[u]) { salida[u] = { ...this.dias[u] }; } }
                    return salida;
                },
                get hayCambios() { return Object.keys(this.cambios).length > 0; },
                get objetivo() { return this.uids.filter(u => !this.seleccion.length || this.seleccion.includes(u)); },
                cobertura(f, codigo) { let n = 0; for (const u of this.uids) { if (this.dias[u][f] === codigo) { n++; } } return n; },
                clase(codigo) { return this.clases[codigo] ?? 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800 text-gray-300'; },
                sigla(codigo) { return this.siglas[codigo] ?? '·'; },
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
                    <button type="button" @click="navegar('irAHoy')" class="px-3 py-1.5 rounded-md border text-sm font-semibold text-ocean-700 dark:text-ocean-300 hover:bg-ocean-50 dark:hover:bg-ocean-500/10 transition-colors">Hoy</button>
                    <span class="text-sm font-semibold w-32 text-center capitalize">{{ $inicioMes->translatedFormat('F Y') }}</span>
                    <button type="button" @click="navegar('mesSiguiente')" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" aria-label="Mes siguiente">Siguiente &rarr;</button>
                </div>
                <span x-show="hayCambios" x-cloak class="text-xs font-medium text-amber-700 dark:text-amber-300">
                    Cambios sin guardar en <span x-text="Object.keys(cambios).length"></span> trabajador(es)
                </span>
            </div>

            @if ($equipo->isEmpty())
                <div class="glass-card p-6 text-sm text-gray-500">
                    No tienes trabajadores 728 activos a cargo en este momento.
                </div>
            @else
                {{-- Pincel y patrón --}}
                <div class="glass-card p-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium mr-1">Turno a pintar:</span>
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
                                :class="[pincel === '{{ $codigo }}' ? 'ring-2 ring-ocean-500 dark:ring-ocean-400 shadow' : 'opacity-80 hover:opacity-100', '{{ $codigo }}' === 'BORRAR' ? 'bg-white dark:bg-gray-900 text-gray-500 border-gray-300 dark:border-gray-700' : clase('{{ $codigo }}')]"
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
                            <label class="block text-xs font-medium mb-1">Repetir patrón</label>
                            <input type="text" x-model="patron" class="w-52 rounded-md border-gray-300 dark:bg-gray-800 text-sm" placeholder="M M T T N D">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">desde el</label>
                            <input type="date" x-model="desde" :min="fechas[0]" :max="fechas[fechas.length - 1]" class="rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" title="Cada trabajador de la lista arranca el patrón este número de días después del anterior">Escalonar (días)</label>
                            <input type="number" min="-30" max="30" x-model="desfase" class="w-20 rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                        </div>
                        <button type="button" @click="aplicarPatron()" class="px-3 py-2 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                            Aplicar a <span x-text="seleccion.length ? seleccion.length + ' marcado(s)' : 'todos'"></span>
                        </button>
                        <button type="button" @click="limpiar()" class="px-3 py-2 rounded-md border text-sm text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800">Limpiar mes</button>
                    </div>
                    <p class="text-xs text-gray-500">
                        Marca filas con la casilla para aplicar el patrón o limpiar solo a esas personas; sin marcar, aplica a todas.
                        Con "Escalonar" en 1, la 2.ª persona arranca el patrón un día después de la 1.ª, la 3.ª dos días, y así.
                    </p>
                    <p x-show="errorPatron" x-text="errorPatron" x-cloak class="text-sm text-red-600"></p>
                </div>

                {{-- Matriz --}}
                <div class="glass-card overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-1 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2 sticky left-0 z-10 bg-white dark:bg-gray-900">
                                    <label class="inline-flex items-center gap-2 normal-case">
                                        <input type="checkbox" class="rounded border-gray-300"
                                            :checked="seleccion.length === uids.length"
                                            @change="seleccion = $event.target.checked ? [...uids] : []">
                                        Trabajador
                                    </label>
                                </th>
                                @foreach ($fechas as $fecha)
                                    <th class="px-1 py-1 text-center font-medium {{ $fecha->toDateString() === $hoy ? 'text-ocean-700 dark:text-ocean-300 font-bold' : ($fecha->isWeekend() ? 'text-rose-500' : '') }}">
                                        <div>{{ $fecha->day }}</div>
                                        <div class="text-[10px] normal-case opacity-70">{{ mb_substr($fecha->translatedFormat('D'), 0, 1) }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody
                            @mousedown="iniciar($event)"
                            @mouseover="celda($event, true)"
                            @click="celda($event, false)"
                        >
                            @foreach ($equipo as $trabajador)
                                <tr wire:key="fila-{{ $trabajador->id }}">
                                    <td class="px-3 py-1 sticky left-0 z-10 bg-white dark:bg-gray-900 whitespace-nowrap align-middle">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" class="rounded border-gray-300" value="{{ $trabajador->id }}" x-model="seleccion">
                                            {{ $trabajador->nombre_completo }}
                                        </label>
                                        <a href="{{ route('turnos.programacion', $trabajador) }}" class="ml-2 text-xs text-ocean-700 dark:text-ocean-300 underline hover:no-underline" title="Programar solo a esta persona">vista individual</a>
                                    </td>
                                    @foreach ($fechas as $fecha)
                                        @php($f = $fecha->toDateString())
                                        <td class="p-0">
                                            <button
                                                type="button"
                                                data-u="{{ $trabajador->id }}"
                                                data-f="{{ $f }}"
                                                :class="clase(dias['{{ $trabajador->id }}']['{{ $f }}'])"
                                                class="w-9 h-9 flex items-center justify-center rounded-md border text-xs font-semibold select-none cursor-pointer transition-colors {{ $f === $hoy ? 'ring-2 ring-ocean-500 dark:ring-ocean-400' : '' }}"
                                                aria-label="{{ $trabajador->nombre_completo }}, {{ $fecha->translatedFormat('l j') }}"
                                            ><span x-text="sigla(dias['{{ $trabajador->id }}']['{{ $f }}'])"></span></button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- Cobertura: cuántas personas hay en cada turno cada día --}}
                            @foreach (['MANANA' => 'M', 'TARDE' => 'T', 'NOCHE' => 'N'] as $codigo => $sigla)
                                <tr class="text-center text-xs">
                                    <td class="px-3 py-1 sticky left-0 z-10 bg-white dark:bg-gray-900 text-left text-gray-500 whitespace-nowrap">Cubren {{ $sigla }}</td>
                                    @foreach ($fechas as $fecha)
                                        <td
                                            x-text="cobertura('{{ $fecha->toDateString() }}', '{{ $codigo }}')"
                                            :class="cobertura('{{ $fecha->toDateString() }}', '{{ $codigo }}') === 0 ? 'text-gray-300 dark:text-gray-600' : 'font-semibold text-gray-700 dark:text-gray-200'"
                                        ></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tfoot>
                    </table>
                </div>

                @error('dias') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($mensaje)
                    <p class="text-sm text-green-700 dark:text-green-400">{{ $mensaje }}</p>
                @endif

                {{-- Advertencias no bloqueantes: hay que confirmar para guardar --}}
                @if ($advertencias)
                    <div class="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 p-4 space-y-3">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Revisa antes de guardar:</p>
                        <ul class="list-disc pl-5 text-sm text-amber-800 dark:text-amber-200 space-y-1">
                            @foreach ($advertencias as $advertencia)
                                <li>{{ $advertencia }}</li>
                            @endforeach
                        </ul>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="$wire.guardarIgualmente(cambios)" class="inline-flex items-center px-3 py-1.5 bg-amber-600 text-white rounded-md text-sm">Guardar igualmente</button>
                            <button type="button" wire:click="descartarAdvertencias" class="text-sm text-gray-600 dark:text-gray-300">Seguir editando</button>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('turnos.calendario.equipo') }}" class="text-sm text-gray-500">Volver</a>
                    <button
                        type="button"
                        @click="$wire.guardar(cambios)"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm disabled:opacity-50"
                    >
                        Guardar programación del mes
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
