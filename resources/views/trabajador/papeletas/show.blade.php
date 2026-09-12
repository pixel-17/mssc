@php
    $puedeCancelar = $papeleta->trabajador_id === auth()->id() && $papeleta->estado->equals(\App\States\Papeleta\PendienteJefe::class);
    $puedeMarcarRetorno = $papeleta->trabajador_id === auth()->id() && $papeleta->estado->equals(\App\States\Papeleta\AutorizadaYCorriendo::class) && ! $papeleta->retorno;
    $sustentoPendiente = $papeleta->sustentos->firstWhere('estado', 'pendiente');

    $filas = collect([
        ['Sede', $papeleta->sede->nombre ?? '—'],
        ['Día operativo', $papeleta->dia_operativo?->format('d/m/Y')],
        ['Jefe inmediato', $papeleta->jefeInmediato?->nombre_completo ?? '—'],
        ['Jefe de área', $papeleta->jefeArea?->nombre_completo ? $papeleta->jefeArea->nombre_completo.($papeleta->escalado_jefe_area_at ? ' (escalado)' : '') : '—'],
        ['Hora de salida real', $papeleta->hora_salida_real?->format('d/m/Y H:i') ?? '—'],
        ['Es emergencia', $papeleta->es_emergencia ? 'Sí' : 'No'],
    ]);

    if ($papeleta->autorizado_con_rrhh_fuera_horario) {
        $filas->push(['Revisión post-hoc RRHH', ucfirst($papeleta->revision_posthoc_estado ?? 'pendiente')]);
    }
@endphp

<x-trabajador-layout :titulo="'Papeleta #'.$papeleta->id" :volver-a="route('trabajador.papeletas.index')">
    <div class="space-y-5">
        <x-flash-messages />

        {{-- Ticket grande --}}
        <div class="glass-card">
            <div class="p-5 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-ocean-100/50">Motivo</p>
                    <p class="font-bold text-lg text-ocean-950 dark:text-white leading-snug">{{ $papeleta->motivo->nombre }}</p>
                </div>
                <x-estado-papeleta :estado="$papeleta->estado" class="shrink-0 whitespace-nowrap" />
            </div>

            <div class="mx-5 border-t border-dashed border-ocean-200/70 dark:border-white/15"></div>

            @if ($papeleta->justificacion)
                <div class="px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-ocean-100/50 mb-1">Justificación</p>
                    <p class="text-sm text-ocean-950 dark:text-white/90">{{ $papeleta->justificacion }}</p>
                </div>
            @endif

            @if ($papeleta->motivo_rechazo)
                <div class="mx-5 mb-4 p-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/20">
                    <p class="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">Motivo de rechazo</p>
                    <p class="text-sm text-red-700 dark:text-red-200">{{ $papeleta->motivo_rechazo }}</p>
                </div>
            @endif

            @if ($papeleta->causa_finalizacion_sin_retorno)
                <div class="mx-5 mb-4 p-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-400/20">
                    <p class="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">Causa de finalización sin retorno</p>
                    <p class="text-sm text-red-700 dark:text-red-200">{{ $papeleta->causa_finalizacion_sin_retorno }}</p>
                </div>
            @endif
        </div>

        {{-- Acciones contextuales --}}
        @if ($puedeCancelar)
            <form method="POST" action="{{ route('trabajador.papeletas.cancelar', $papeleta) }}"
                  onsubmit="return confirm('¿Cancelar esta papeleta?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger-glass w-full text-sm py-3">
                    Cancelar papeleta
                </button>
            </form>
        @endif

        @if ($puedeMarcarRetorno)
            <div class="glass-card p-5" x-data="{ lat: '', lng: '', obteniendo: false, error: '', archivoFoto: null }">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80 mb-3">Marcar retorno</h3>

                <form method="POST" action="{{ route('trabajador.papeletas.retorno.store', $papeleta) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <label
                        for="foto-retorno"
                        class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-ocean-200 dark:border-white/15 bg-white/40 dark:bg-white/5 py-4 text-center cursor-pointer hover:border-ocean-400 dark:hover:border-ocean-400/60 transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-ocean-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.132.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.803-2.169a47.865 47.865 0 00-1.132-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                        <span class="text-sm font-medium text-ocean-700 dark:text-ocean-200" x-text="archivoFoto ?? 'Tomar / adjuntar foto'"></span>
                    </label>
                    <input type="file" id="foto-retorno" name="foto" accept="image/*" required class="hidden"
                           @change="archivoFoto = $event.target.files[0]?.name ?? null">

                    <button type="button"
                            @click="obteniendo = true; error = ''; navigator.geolocation.getCurrentPosition(
                                (p) => { lat = p.coords.latitude; lng = p.coords.longitude; obteniendo = false; },
                                (e) => { error = 'No se pudo obtener tu ubicación: ' + e.message; obteniendo = false; }
                            )"
                            class="btn-ocean-outline w-full text-sm py-2.5">
                        <span x-show="!obteniendo">📍 Usar mi ubicación actual</span>
                        <span x-show="obteniendo">Obteniendo ubicación...</span>
                    </button>
                    <p class="text-xs text-red-600 dark:text-red-400" x-show="error" x-text="error"></p>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400" x-show="lat && lng">Ubicación capturada ✓</p>

                    <input type="hidden" name="latitud" :value="lat">
                    <input type="hidden" name="longitud" :value="lng">

                    <button type="submit" :disabled="!lat || !lng" class="btn-ocean w-full text-sm py-3 disabled:opacity-40 disabled:pointer-events-none">
                        Confirmar retorno
                    </button>
                </form>
            </div>
        @endif

        @if ($sustentoPendiente)
            <div class="glass-card p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80 mb-1">Sustento pendiente</h3>
                <p class="text-xs text-gray-500 dark:text-ocean-100/50 mb-3">
                    Fecha límite: {{ $sustentoPendiente->fecha_limite?->format('d/m/Y H:i') }}
                </p>
                <form method="POST" action="{{ route('trabajador.papeletas.sustento.store', $sustentoPendiente) }}" enctype="multipart/form-data" class="space-y-3"
                      x-data="{ archivo: null }">
                    @csrf
                    <label for="archivo-sustento"
                           class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-ocean-200 dark:border-white/15 bg-white/40 dark:bg-white/5 py-4 text-center cursor-pointer hover:border-ocean-400 dark:hover:border-ocean-400/60 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-ocean-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                        <span class="text-sm font-medium text-ocean-700 dark:text-ocean-200" x-text="archivo ?? 'Adjuntar sustento'"></span>
                    </label>
                    <input type="file" id="archivo-sustento" name="archivo" required class="hidden"
                           @change="archivo = $event.target.files[0]?.name ?? null">

                    <button type="submit" class="btn-ocean w-full text-sm py-3">
                        Presentar sustento
                    </button>
                </form>
            </div>
        @endif

        {{-- Detalle --}}
        <div class="glass-card divide-y divide-ocean-100/70 dark:divide-white/10">
            @foreach ($filas as [$etiqueta, $valor])
                <div class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-500 dark:text-ocean-100/50">{{ $etiqueta }}</span>
                    <span class="font-medium text-ocean-950 dark:text-white text-right">{{ $valor ?? '—' }}</span>
                </div>
            @endforeach
        </div>

        {{-- Retorno registrado --}}
        @if ($papeleta->retorno)
            <div class="glass-card p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80 mb-3">Retorno registrado</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-ocean-100/50">Hora del servidor</span>
                        <span class="font-medium text-ocean-950 dark:text-white">{{ $papeleta->retorno->hora_servidor->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-ocean-100/50">Dentro de radio de sede</span>
                        <span class="font-medium text-ocean-950 dark:text-white">
                            @if(is_null($papeleta->retorno->dentro_de_radio)) — @else {{ $papeleta->retorno->dentro_de_radio ? 'Sí' : 'No' }} @endif
                        </span>
                    </div>
                    @if ($papeleta->retorno->marcado_manual)
                        <div class="pt-2 border-t border-ocean-100/70 dark:border-white/10">
                            <p class="text-gray-500 dark:text-ocean-100/50">Marcado manual por falla de conectividad</p>
                            <p class="font-medium text-ocean-950 dark:text-white">{{ $papeleta->retorno->marcadoManualPor?->nombre_completo }} — {{ $papeleta->retorno->justificacion_manual }}</p>
                        </div>
                    @endif
                    @if ($papeleta->descuento_refrigerio_minutos)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-ocean-100/50">Descuento de refrigerio</span>
                            <span class="font-medium text-ocean-950 dark:text-white">{{ $papeleta->descuento_refrigerio_minutos }} min</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Sustentos --}}
        @if ($papeleta->sustentos->isNotEmpty())
            <div class="glass-card p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80 mb-3">Sustentos</h3>
                <ul class="space-y-2">
                    @foreach ($papeleta->sustentos as $sustento)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-gray-600 dark:text-ocean-100/70">
                                {{ $sustento->presentado_at?->format('d/m/Y H:i') ?? '—' }}
                                · límite {{ $sustento->fecha_limite?->format('d/m/Y H:i') }}
                            </span>
                            <span class="badge-ocean shrink-0 {{ match($sustento->estado) {
                                'aprobado' => 'bg-emerald-50 text-emerald-800 ring-emerald-300 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-400/30',
                                'observado' => 'bg-orange-50 text-orange-800 ring-orange-300 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-400/30',
                                default => 'bg-gray-100 text-gray-700 ring-gray-300 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15',
                            } }}">
                                {{ ucfirst($sustento->estado) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('trabajador.papeletas._timeline', ['papeleta' => $papeleta])
    </div>
</x-trabajador-layout>
