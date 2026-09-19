<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Programar turnos — {{ $trabajador->nombre_completo }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Régimen {{ $trabajador->regimen }}. Elige un turno y haz clic (o arrastra) sobre los días.
                Lo que guardes aquí reemplaza la programación de ese mes y el mes siguiente no se genera solo
                sobre él.
            </p>
        </div>

        {{-- wire:key con $version: al cambiar de mes o guardar, Alpine reinicia la grilla desde la BD. --}}
        <div
            wire:key="programacion-{{ $version }}"
            @mouseup.window="arrastrando = false"
            x-data="{
                dias: @js($diasIniciales),
                fechasMes: @js($fechasMes),
                original: '',
                pincel: 'MANANA',
                arrastrando: false,
                patron: 'M M M M M M D',
                desde: @js($fechasMes[0] ?? ''),
                errorPatron: '',
                claves: { M: 'MANANA', T: 'TARDE', N: 'NOCHE', D: 'DESCANSO' },
                siglas: { MANANA: 'M', TARDE: 'T', NOCHE: 'N', DESCANSO: 'D' },
                clases: @js(\App\Support\TurnoColores::porCodigo()),
                init() { this.original = this.firma(); },
                firma() { return JSON.stringify(Object.entries(this.dias).sort()); },
                get sucio() { return this.firma() !== this.original; },
                get sinProgramar() { return this.fechasMes.filter(f => !this.dias[f]).length; },
                cuenta(codigo) { return this.fechasMes.filter(f => this.dias[f] === codigo).length; },
                clase(codigo) { return this.clases[codigo] ?? 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800 text-gray-400'; },
                sigla(codigo) { return this.siglas[codigo] ?? '—'; },
                pintar(fecha) {
                    if (this.pincel === 'BORRAR') { delete this.dias[fecha]; } else { this.dias[fecha] = this.pincel; }
                },
                aplicarPatron() {
                    const pasos = this.patron.toUpperCase().split(/[\s,]+/).filter(Boolean).map(t => this.claves[t]);
                    if (!pasos.length || pasos.includes(undefined)) {
                        this.errorPatron = 'Usa solo M, T, N o D separados por espacios. Ej.: M M T T N D';
                        return;
                    }
                    this.errorPatron = '';
                    let i = 0;
                    for (const f of this.fechasMes.filter(f => f >= this.desde)) { this.dias[f] = pasos[i++ % pasos.length]; }
                },
                navegar(accion) {
                    if (!this.sucio || confirm('Tienes cambios sin guardar. ¿Descartarlos?')) { this.$wire[accion](); }
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
                <span x-show="sucio" x-cloak class="text-xs font-medium text-amber-700 dark:text-amber-300">Cambios sin guardar</span>
            </div>

            {{-- Pincel --}}
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

                {{-- Patrón repetido: atajo para ciclos regulares --}}
                <div class="flex flex-wrap items-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <label class="block text-xs font-medium mb-1">Repetir patrón</label>
                        <input type="text" x-model="patron" class="w-56 rounded-md border-gray-300 dark:bg-gray-800 text-sm" placeholder="M M T T N D">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">desde el</label>
                        <input type="date" x-model="desde" :min="fechasMes[0]" :max="fechasMes[fechasMes.length - 1]" class="rounded-md border-gray-300 dark:bg-gray-800 text-sm">
                    </div>
                    <button type="button" @click="aplicarPatron()" class="px-3 py-2 rounded-md border text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Aplicar hasta fin de mes</button>
                    <button type="button" @click="dias = {}" class="px-3 py-2 rounded-md border text-sm text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800">Limpiar mes</button>
                </div>
                <p x-show="errorPatron" x-text="errorPatron" x-cloak class="text-sm text-red-600"></p>
            </div>

            {{-- Calendario --}}
            <div class="glass-card p-4 overflow-x-auto">
                <table class="min-w-full text-center text-sm border-separate border-spacing-1">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $nombreDia)
                                <th class="px-2 py-2">{{ $nombreDia }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($semanas as $semana)
                            <tr>
                                @foreach ($semana as $fecha)
                                    <td class="align-top p-0">
                                        @if ($fecha)
                                            <button
                                                type="button"
                                                @mousedown.prevent="arrastrando = true; pintar('{{ $fecha }}')"
                                                @mouseenter="if (arrastrando) pintar('{{ $fecha }}')"
                                                @click="pintar('{{ $fecha }}')"
                                                :class="clase(dias['{{ $fecha }}'])"
                                                class="w-full h-16 rounded-lg border px-2 py-2 flex flex-col justify-between select-none cursor-pointer transition-colors {{ $fecha === $hoy ? 'ring-2 ring-ocean-500 dark:ring-ocean-400' : '' }}"
                                                aria-label="{{ \Illuminate\Support\Carbon::parse($fecha)->translatedFormat('l j') }}"
                                            >
                                                <span class="text-xs {{ $fecha === $hoy ? 'font-bold' : 'opacity-70' }} text-left">{{ (int) substr($fecha, 8, 2) }}</span>
                                                <span class="font-semibold text-right" x-text="sigla(dias['{{ $fecha }}'])"></span>
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Resumen --}}
            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                <span>Mañana: <strong x-text="cuenta('MANANA')"></strong></span>
                <span>Tarde: <strong x-text="cuenta('TARDE')"></strong></span>
                <span>Noche: <strong x-text="cuenta('NOCHE')"></strong></span>
                <span>Descanso: <strong x-text="cuenta('DESCANSO')"></strong></span>
                <span>Sin programar: <strong x-text="sinProgramar"></strong></span>
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
                        <button type="button" @click="$wire.guardarIgualmente(dias)" class="inline-flex items-center px-3 py-1.5 bg-amber-600 text-white rounded-md text-sm">Guardar igualmente</button>
                        <button type="button" wire:click="descartarAdvertencias" class="text-sm text-gray-600 dark:text-gray-300">Seguir editando</button>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('turnos.calendario.equipo') }}" class="text-sm text-gray-500">Volver</a>
                <button
                    type="button"
                    @click="$wire.guardar(dias)"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm disabled:opacity-50"
                >
                    Guardar programación del mes
                </button>
            </div>
        </div>
    </div>
</div>
