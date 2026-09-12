@php
    use App\States\Papeleta\PendienteJefe;
    use App\States\Papeleta\ObservadaPorRrhh;
    use App\States\Papeleta\AutorizadaYCorriendo;
    use App\States\Papeleta\RetornoPendienteSustento;

    $puedeDecidir = $papeleta->estado->equals(PendienteJefe::class)
        && (($papeleta->escalado_jefe_area_at !== null && $papeleta->jefe_area_id === auth()->id())
            || ($papeleta->escalado_jefe_area_at === null && $papeleta->jefe_inmediato_id === auth()->id()));

    $puedeReconocer = $papeleta->estado->equals(ObservadaPorRrhh::class) && $papeleta->jefe_inmediato_id === auth()->id();

    $enCurso = $papeleta->estado->equals(AutorizadaYCorriendo::class) && $papeleta->jefe_inmediato_id === auth()->id();

    $sustentoPresentado = $papeleta->estado->equals(RetornoPendienteSustento::class)
        ? $papeleta->sustentos->firstWhere('estado', 'presentado')
        : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Papeleta #{{ $papeleta->id }} · {{ $papeleta->trabajador->nombre_completo }}
            </h2>
            <x-estado-papeleta :estado="$papeleta->estado" class="text-sm" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            @include('papeletas._info', ['papeleta' => $papeleta])

            @if ($puedeDecidir)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Decisión</h3>
                    <div class="flex items-center gap-3 flex-wrap">
                        <form method="POST" action="{{ route('jefe.papeletas.aprobar', $papeleta) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md text-white bg-green-600 hover:bg-green-700">Aprobar</button>
                        </form>
                        <x-accion-comentario :action="route('jefe.papeletas.observar', $papeleta)" label="Observar" color="orange" />
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
                <div class="glass-card p-6" x-data="{ resultado: 'aprobado' }">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Revisar sustento presentado</h3>
                    <form method="POST" action="{{ route('jefe.sustentos.revisar', $sustentoPresentado) }}" class="space-y-3">
                        @csrf
                        <div class="flex items-center gap-4 text-sm">
                            <label class="inline-flex items-center gap-1">
                                <input type="radio" name="resultado" value="aprobado" x-model="resultado"> Aprobar
                            </label>
                            <label class="inline-flex items-center gap-1">
                                <input type="radio" name="resultado" value="observado" x-model="resultado"> Observar
                            </label>
                        </div>
                        <textarea name="comentario" rows="2" maxlength="2000" x-show="resultado === 'observado'"
                                  placeholder="Motivo de la observación (mínimo 5 caracteres)..."
                                  class="block w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-ocean-500 focus:ring-ocean-500"></textarea>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md text-white bg-ocean-600 hover:bg-ocean-700">
                            Confirmar revisión
                        </button>
                    </form>
                </div>
            @endif

            @include('papeletas._historial', ['papeleta' => $papeleta])
        </div>
    </div>
</x-app-layout>
