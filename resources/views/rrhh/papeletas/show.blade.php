@php
    use App\States\Papeleta\PendienteRrhh;
    use App\States\Papeleta\EnJustificacion;

    // Solo lectura cuando la ve el admin (ver Admin\PapeletaController):
    // ninguna acción de RRHH aparece para él.
    $soloLectura = $soloLectura ?? false;

    $puedeDecidir = ! $soloLectura && $papeleta->estado->equals(PendienteRrhh::class);

    $puedePosthoc = ! $soloLectura && $papeleta->autorizado_con_rrhh_fuera_horario
        && in_array($papeleta->revision_posthoc_estado, ['pendiente', 'respondida'], true);

    $topePosthoc = (int) \App\Models\Configuracion::valorDe('TOPE_OBSERVACIONES', 3);
    $posthocRespondida = $papeleta->revision_posthoc_estado === 'respondida';

    $sustentoPresentado = ! $soloLectura && $papeleta->estado->equals(EnJustificacion::class)
        ? $papeleta->sustentos->firstWhere('estado', 'presentado')
        : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
                Papeleta #{{ $papeleta->id }} · {{ $papeleta->trabajador->nombre_completo }}
            </h2>
            <span data-en-vivo-estado><x-estado-papeleta :estado="$papeleta->estado" :abandono="$papeleta->esAbandono()" class="text-sm" /></span>
        </div>
    </x-slot>

    {{-- Aviso de bloqueo suave: otro usuario de RRHH ya la está revisando. --}}
    @isset($enRevisionPor)
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700 px-4 py-3 text-sm text-amber-800 dark:text-amber-200" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>
                    <strong>{{ $enRevisionPor }}</strong> ya está revisando esta papeleta.
                    Puedes verla, pero coordina antes de tomar una decisión.
                </span>
            </div>
        </div>
    @endisset

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <x-papeleta-en-vivo :papeleta="$papeleta" />
        </div>

        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6" data-en-vivo-contenido>
            @include('papeletas._info', ['papeleta' => $papeleta])

            @if ($puedeDecidir || $puedePosthoc)
                @php
                    $fmtMin = fn (int $m) => intdiv($m, 60).' h '.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT).' min';
                @endphp
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">
                        Historial de {{ $papeleta->trabajador->nombre_completo }} en {{ \Carbon\Carbon::createFromFormat('Y-m', $historialMes['mes'])->translatedFormat('F Y') }}
                    </h3>
                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Otras papeletas</dt>
                            <dd class="font-semibold text-gray-900">{{ $historialMes['papeletas'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Rechazadas</dt>
                            <dd class="font-semibold text-gray-900">{{ $historialMes['rechazadas'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Observaciones</dt>
                            <dd class="font-semibold text-gray-900">{{ $historialMes['observaciones'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Tiempo fuera / con descuento</dt>
                            <dd class="font-semibold text-gray-900">{{ $fmtMin($historialMes['minutos']) }} / {{ $fmtMin($historialMes['minutos_con_descuento']) }}</dd>
                        </div>
                    </dl>
                    <a href="{{ route('reportes.trabajador-historial', ['trabajadorId' => $papeleta->trabajador_id]) }}" class="mt-3 inline-block text-xs underline text-gray-500">Ver historial completo</a>
                </div>
            @endif

            @if ($puedeDecidir)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Decisión de RRHH</h3>
                    <div class="flex items-center gap-3 flex-wrap">
                        <x-boton-confirmar
                            :action="route('rrhh.papeletas.aprobar', $papeleta)"
                            label="Aprobar"
                            color="green"
                        />
                        @unless ($papeleta->sinJefatura())
                            <x-accion-comentario :action="route('rrhh.papeletas.observar', $papeleta)" label="Observar (vuelve al jefe)" color="orange" :sugerencias="config('respuestas_rapidas.rrhh_observar')" />
                        @endunless
                        <x-accion-comentario :action="route('rrhh.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" :sugerencias="config('respuestas_rapidas.rrhh_rechazar')" />
                    </div>
                    @if ($papeleta->contador_observaciones_rrhh > 0)
                        <p class="text-xs text-gray-500 mt-2">Observaciones previas de RRHH: {{ $papeleta->contador_observaciones_rrhh }}/3</p>
                    @endif
                </div>
            @endif

            @if ($puedePosthoc)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Revisión posterior</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        El jefe inmediato autorizó esta papeleta fuera del horario de RRHH ({{ $papeleta->resueltoPorJefe?->nombre_completo ?? '—' }}). Deja constancia de la revisión.
                    </p>
                    @if ($posthocRespondida)
                        <div class="mb-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                            <p class="font-medium">El jefe respondió tu observación:</p>
                            <p class="mt-1">{{ $papeleta->posthoc_respuesta }}</p>
                            @if ($papeleta->posthoc_adjunto_path)
                                <a href="{{ route('papeletas.archivo', [$papeleta, 'respuesta-posthoc']) }}" class="mt-1 inline-block text-xs underline">Ver adjunto</a>
                            @endif
                        </div>
                    @endif
                    @if ($papeleta->contador_observaciones_posthoc > 0)
                        <p class="text-xs text-gray-500 mb-3">
                            Observaciones de la revisión posterior: {{ $papeleta->contador_observaciones_posthoc }}/{{ $topePosthoc }}.
                            @if ($papeleta->contador_observaciones_posthoc >= $topePosthoc - 1)
                                Si observas de nuevo, quedará como reparo definitivo, sin más respuestas.
                            @endif
                        </p>
                    @endif
                    <div class="flex items-center gap-3 flex-wrap">
                        <x-boton-confirmar
                            :action="route('rrhh.papeletas.posthoc-aprobar', $papeleta)"
                            label="Aprobar revisión"
                            color="green"
                        />
                        <x-accion-comentario :action="route('rrhh.papeletas.posthoc-observar', $papeleta)" label="Observar revisión" color="orange" :sugerencias="config('respuestas_rapidas.rrhh_posthoc_observar')" />
                        <x-accion-comentario
                            :action="route('rrhh.papeletas.posthoc-no-aprobar', $papeleta)"
                            label="No aprobar"
                            color="red"
                            aviso="La papeleta pasará a Particular: se descontarán las horas que el trabajador estuvo fuera. Esta decisión no se puede deshacer desde aquí."
                        />
                    </div>
                </div>
            @endif

            @if ($sustentoPresentado)
                <div class="glass-card p-6" x-data="{ resultado: 'aprobado', enviando: false }">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Revisar sustento presentado</h3>
                    <form method="POST" action="{{ route('rrhh.sustentos.revisar', $sustentoPresentado) }}" class="space-y-3" @submit="enviando = true">
                        @csrf
                        <div class="flex items-center gap-4 text-sm flex-wrap">
                            <label class="inline-flex items-center gap-1">
                                <input type="radio" name="resultado" value="aprobado" x-model="resultado"> Aprobar
                            </label>
                            <label class="inline-flex items-center gap-1">
                                <input type="radio" name="resultado" value="observado" x-model="resultado"> Observar
                            </label>
                            <label class="inline-flex items-center gap-1">
                                <input type="radio" name="resultado" value="particular" x-model="resultado"> Cambiar a Particular (con descuento)
                            </label>
                        </div>
                        <p x-show="resultado === 'particular'" x-cloak class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                            El sustento no es válido y no se le dará una nueva oportunidad: la papeleta pasará a Particular y se descontarán las horas que el trabajador estuvo fuera. Esta decisión no se puede deshacer desde aquí.
                        </p>
                        <textarea name="comentario" aria-label="Motivo" rows="2" maxlength="2000" x-show="resultado === 'observado' || resultado === 'particular'"
                                  placeholder="Motivo (mínimo 5 caracteres)..."
                                  class="block w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea>
                        <button type="submit" :disabled="enviando" :class="{ 'opacity-50 cursor-not-allowed': enviando }" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md text-white bg-tinta-600 hover:bg-tinta-700">
                            <span x-show="! enviando">Confirmar revisión</span>
                            <span x-show="enviando" x-cloak>Enviando…</span>
                        </button>
                    </form>
                </div>
            @endif

            @can('corregirComoRrhh', $papeleta)
                @php
                    $campo = 'block w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 dark:border-white/15 dark:bg-white/5 dark:text-white';
                @endphp
                <div class="glass-card p-6" x-data="{ abierto: {{ $errors->any() || old('comentario') ? 'true' : 'false' }}, enviando: false }">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Corregir datos de la papeleta</h3>
                        <button type="button" class="text-xs underline text-gray-500" @click="abierto = ! abierto" x-text="abierto ? 'Cerrar' : 'Abrir'"></button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Para errores de registro (hora de retorno mal marcada, motivo equivocado). No reabre la papeleta y la corrección queda en el historial.</p>
                    <form x-show="abierto" x-cloak method="POST" action="{{ route('rrhh.papeletas.corregir', $papeleta) }}" class="mt-4 space-y-3" @submit="enviando = true">
                        @csrf
                        @if ($papeleta->retorno)
                            <div>
                                <label for="corregir-hora" class="block text-xs font-medium text-gray-600 mb-1">
                                    {{ $papeleta->retorno->generado_por_abandono ? 'Hora de fin de turno/día (sin retorno marcado — actual' : 'Hora de retorno (actual' }}: {{ $papeleta->retorno->hora_servidor?->format('d/m/Y H:i') }})
                                </label>
                                <input id="corregir-hora" type="datetime-local" name="hora_retorno" value="{{ old('hora_retorno') }}" class="{{ $campo }}">
                            </div>
                        @endif
                        <div>
                            <label for="corregir-motivo" class="block text-xs font-medium text-gray-600 mb-1">Motivo (actual: {{ $papeleta->motivo->nombre }})</label>
                            <select id="corregir-motivo" name="motivo_id" class="{{ $campo }}">
                                <option value="">No cambiar</option>
                                @foreach ($motivosActivos->where('id', '!=', $papeleta->motivo_id) as $m)
                                    <option value="{{ $m->id }}" @selected((int) old('motivo_id') === $m->id)>{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="corregir-comentario" class="block text-xs font-medium text-gray-600 mb-1">Justificación (obligatoria, mínimo {{ \App\Actions\Papeleta\CorregirPapeletaRrhhAction::MIN_JUSTIFICACION }} caracteres)</label>
                            <textarea id="corregir-comentario" name="comentario" rows="3" required minlength="{{ \App\Actions\Papeleta\CorregirPapeletaRrhhAction::MIN_JUSTIFICACION }}" maxlength="2000" class="{{ $campo }}">{{ old('comentario') }}</textarea>
                            @error('comentario')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            @error('hora_retorno')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" :disabled="enviando" :class="{ 'opacity-50 cursor-not-allowed': enviando }" class="inline-flex items-center px-4 py-2 text-xs font-semibold rounded-md text-white bg-tinta-600 hover:bg-tinta-700">
                            <span x-show="! enviando">Guardar corrección</span>
                            <span x-show="enviando" x-cloak>Guardando…</span>
                        </button>
                    </form>
                </div>
            @endcan

            @include('papeletas._historial', ['papeleta' => $papeleta])
        </div>
    </div>
</x-app-layout>
