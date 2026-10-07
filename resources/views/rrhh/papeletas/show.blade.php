@php
    use App\States\Papeleta\PendienteRrhh;
    use App\States\Papeleta\RetornoPendienteSustento;

    $puedeDecidir = $papeleta->estado->equals(PendienteRrhh::class);

    $puedePosthoc = $papeleta->autorizado_con_rrhh_fuera_horario
        && in_array($papeleta->revision_posthoc_estado, ['pendiente', 'respondida'], true);

    $topePosthoc = (int) \App\Models\Configuracion::valorDe('TOPE_OBSERVACIONES_RRHH', 3);
    $posthocRespondida = $papeleta->revision_posthoc_estado === 'respondida';

    $sustentoPresentado = $papeleta->estado->equals(RetornoPendienteSustento::class)
        ? $papeleta->sustentos->firstWhere('estado', 'presentado')
        : null;

    $puedeMarcarAbandono = $papeleta->estado->equals(RetornoPendienteSustento::class);
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
                            confirmText="¿Aprobar la papeleta de {{ $papeleta->trabajador->nombre_completo }}?"
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
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Revisión post-hoc</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        El jefe inmediato autorizó esta papeleta fuera del horario de RRHH ({{ $papeleta->resueltoPorJefe?->nombre_completo ?? '—' }}). Deja constancia de la revisión.
                    </p>
                    @if ($posthocRespondida)
                        <div class="mb-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                            <p class="font-medium">El jefe respondió tu observación:</p>
                            <p class="mt-1">{{ $papeleta->posthoc_respuesta }}</p>
                            @if ($papeleta->posthoc_adjunto_path)
                                <a href="{{ route('papeletas.archivo', [$papeleta, 'respuesta-posthoc']) }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs underline">Ver adjunto</a>
                            @endif
                        </div>
                    @endif
                    @if ($papeleta->contador_observaciones_posthoc > 0)
                        <p class="text-xs text-gray-500 mb-3">
                            Observaciones post-hoc: {{ $papeleta->contador_observaciones_posthoc }}/{{ $topePosthoc }}.
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
                            confirmText="¿Aprobar la revisión post-hoc de la papeleta de {{ $papeleta->trabajador->nombre_completo }}?"
                        />
                        <x-accion-comentario :action="route('rrhh.papeletas.posthoc-observar', $papeleta)" label="Observar revisión" color="orange" :sugerencias="config('respuestas_rapidas.rrhh_posthoc_observar')" />
                    </div>
                </div>
            @endif

            @if ($sustentoPresentado)
                <div class="glass-card p-6" x-data="{ resultado: 'aprobado', enviando: false }">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Revisar sustento presentado</h3>
                    <form method="POST" action="{{ route('rrhh.sustentos.revisar', $sustentoPresentado) }}" class="space-y-3" @submit="enviando = true">
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

            @if ($puedeMarcarAbandono)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Abandono sobre retorno pendiente de sustento</h3>
                    <p class="text-xs text-gray-500 mb-3">Usar solo si corresponde precedencia de abandono sobre el sustento vencido.</p>
                    <x-accion-comentario :action="route('rrhh.papeletas.marcar-abandono', $papeleta)" label="Marcar abandono" color="red" confirmText="¿Confirmas marcar esta papeleta como abandono?" />
                </div>
            @endif

            @can('corregirComoRrhh', $papeleta)
                @php
                    $motivosActivos = \App\Models\Motivo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
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
                                <label for="corregir-hora" class="block text-xs font-medium text-gray-600 mb-1">Hora de retorno (actual: {{ $papeleta->retorno->hora_servidor?->format('d/m/Y H:i') }})</label>
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
