@php
    use App\States\Papeleta\ObservadaPorJefe;
    use App\States\Papeleta\PendienteJefe;
    use App\States\Papeleta\ObservadaPorRrhh;
    use App\States\Papeleta\AutorizadaYCorriendo;
    use App\States\Papeleta\RetornoPendienteSustento;

    $estaPendiente = $papeleta->estado->equals(PendienteJefe::class);
    $estaObservada = $papeleta->estado->equals(ObservadaPorJefe::class);

    // Tras observar, mientras el trabajador no responda el jefe solo puede rechazar
    // (al responder, la papeleta vuelve a PENDIENTE_JEFE y decide como siempre).
    $puedeDecidir = ($estaPendiente || $estaObservada) && $papeleta->jefe_inmediato_id === auth()->id();

    $puedeReconocer = $papeleta->estado->equals(ObservadaPorRrhh::class) && $papeleta->jefe_inmediato_id === auth()->id();

    $enCurso = $papeleta->estado->equals(AutorizadaYCorriendo::class) && $papeleta->jefe_inmediato_id === auth()->id();

    $sustentoPresentado = $papeleta->estado->equals(RetornoPendienteSustento::class)
        ? $papeleta->sustentos->firstWhere('estado', 'presentado')
        : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
                Papeleta #{{ $papeleta->id }} · {{ $papeleta->trabajador->nombre_completo }}
            </h2>
            <span data-en-vivo-estado><x-estado-papeleta :estado="$papeleta->estado" class="text-sm" /></span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <x-papeleta-en-vivo :papeleta="$papeleta" />
        </div>

        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6" data-en-vivo-contenido>
            @include('papeletas._info', ['papeleta' => $papeleta])

            @if ($puedeDecidir)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Decisión</h3>

                    @if ($estaObservada)
                        <p class="text-sm text-gray-600 mb-3">
                            Observaste esta papeleta. Espera la respuesta escrita del trabajador{{ $papeleta->observacion_requiere_adjunto ? ' (con archivo adjunto)' : '' }}:
                            volverá a tu bandeja para que decidas. Mientras tanto solo puedes rechazarla.
                        </p>
                    @endif

                    <div class="flex items-center gap-3 flex-wrap">
                        @if ($estaPendiente)
                            <form method="POST" action="{{ route('jefe.papeletas.aprobar', $papeleta) }}" x-data="{ enviando: false }" @submit="enviando = true">
                                @csrf
                                <button type="submit" :disabled="enviando" :class="{ 'opacity-50 cursor-not-allowed': enviando }" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md text-white bg-green-600 hover:bg-green-700">
                                    <span x-show="! enviando">Aprobar</span>
                                    <span x-show="enviando" x-cloak>Aprobando…</span>
                                </button>
                            </form>
                        @endif
                        @if ($estaPendiente)
                            <x-accion-comentario :action="route('jefe.papeletas.observar', $papeleta)" label="Observar" color="orange" opcion="requiere_adjunto" opcionLabel="Además de responder por escrito, debe adjuntar un archivo" :opcionMarcada="false" />
                        @endif
                        <x-accion-comentario :action="route('jefe.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" />
                    </div>
                    @if ($papeleta->contador_observaciones_jefe > 0)
                        <p class="text-xs text-gray-400 mt-2">Observaciones previas del jefe: {{ $papeleta->contador_observaciones_jefe }}/3</p>
                    @endif
                </div>
            @endif

            @if ($puedeReconocer)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Observación de RRHH</h3>
                    <p class="text-sm text-gray-600 mb-3">RRHH observó esta papeleta. Revisa el historial y reconoce para reabrirla en tu bandeja.</p>
                    <x-accion-comentario :action="route('jefe.papeletas.reconocer-observacion-rrhh', $papeleta)" label="Reconocer" color="orange" />
                </div>
            @endif

            @if ($enCurso)
                <div class="glass-card p-6 space-y-6">
                    <h3 class="text-sm font-semibold text-gray-700">Papeleta en curso</h3>

                    @if (! $papeleta->retorno)
                        <div>
                            <p class="text-xs text-gray-500 mb-2">Retorno manual (solo ante falla de conectividad del trabajador):</p>
                            <x-accion-comentario :action="route('jefe.papeletas.retorno-manual', $papeleta)" label="Marcar retorno manual" color="gray" field="justificacion" :minlength="10" placeholder="Justifica la falla de conectividad (mínimo 10 caracteres)..." confirmText="¿Confirmas el retorno manual por falla de conectividad?" />
                        </div>
                    @endif

                    @if ($papeleta->motivo->permite_cierre_sin_retorno)
                        <div>
                            <p class="text-xs text-gray-500 mb-2">Comisión de servicio sin retorno físico:</p>
                            <form method="POST" action="{{ route('jefe.papeletas.cerrar-sin-retorno', $papeleta) }}" onsubmit="return confirm('¿Cerrar esta papeleta sin retorno físico?')">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-semibold rounded-md text-white bg-gray-600 hover:bg-gray-700">Cerrar sin retorno físico</button>
                            </form>
                        </div>
                    @endif

                    <div>
                        <p class="text-xs text-gray-500 mb-2">Marcar abandono (no retornó y no hay justificación válida):</p>
                        <x-accion-comentario :action="route('jefe.papeletas.marcar-abandono', $papeleta)" label="Marcar abandono" color="red" confirmText="¿Confirmas marcar esta papeleta como abandono?" />
                    </div>
                </div>
            @endif

            @if ($sustentoPresentado)
                <div class="glass-card p-6" x-data="{ resultado: 'aprobado', enviando: false }">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Revisar sustento presentado</h3>
                    <form method="POST" action="{{ route('jefe.sustentos.revisar', $sustentoPresentado) }}" class="space-y-3" @submit="enviando = true">
                        @csrf
                        <div class="flex items-center gap-4 text-sm">
                            <label class="inline-flex items-center gap-1">
                                <input type="radio" name="resultado" value="aprobado" x-model="resultado"> Aprobar
                            </label>
                            <label class="inline-flex items-center gap-1">
                                <input type="radio" name="resultado" value="observado" x-model="resultado"> Observar
                            </label>
                        </div>
                        <textarea name="comentario" aria-label="Motivo de la observación" rows="2" maxlength="2000" x-show="resultado === 'observado'"
                                  placeholder="Motivo de la observación (mínimo 5 caracteres)..."
                                  class="block w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea>
                        <button type="submit" :disabled="enviando" :class="{ 'opacity-50 cursor-not-allowed': enviando }" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md text-white bg-tinta-600 hover:bg-tinta-700">
                            <span x-show="! enviando">Confirmar revisión</span>
                            <span x-show="enviando" x-cloak>Enviando…</span>
                        </button>
                    </form>
                </div>
            @endif

            @include('papeletas._historial', ['papeleta' => $papeleta])
        </div>
    </div>
</x-app-layout>
