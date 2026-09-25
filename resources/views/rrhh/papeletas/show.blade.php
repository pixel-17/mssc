@php
    use App\States\Papeleta\PendienteRrhh;
    use App\States\Papeleta\RetornoPendienteSustento;

    $puedeDecidir = $papeleta->estado->equals(PendienteRrhh::class);

    $puedePosthoc = $papeleta->autorizado_con_rrhh_fuera_horario
        && $papeleta->revision_posthoc_estado === 'pendiente';

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
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Decisión de RRHH</h3>
                    <div class="flex items-center gap-3 flex-wrap">
                        <x-boton-confirmar
                            :action="route('rrhh.papeletas.aprobar', $papeleta)"
                            label="Aprobar"
                            color="green"
                            confirmText="¿Aprobar la papeleta de {{ $papeleta->trabajador->nombre_completo }}?"
                        />
                        @unless ($papeleta->sinJefatura())
                            <x-accion-comentario :action="route('rrhh.papeletas.observar', $papeleta)" label="Observar (vuelve al jefe)" color="orange" />
                        @endunless
                        <x-accion-comentario :action="route('rrhh.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" />
                    </div>
                    @if ($papeleta->contador_observaciones_rrhh > 0)
                        <p class="text-xs text-gray-400 mt-2">Observaciones previas de RRHH: {{ $papeleta->contador_observaciones_rrhh }}/3</p>
                    @endif
                </div>
            @endif

            @if ($puedePosthoc)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Revisión post-hoc</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        El jefe inmediato autorizó esta papeleta fuera del horario de RRHH ({{ $papeleta->resueltoPorJefe?->nombre_completo ?? '—' }}). Deja constancia de la revisión.
                    </p>
                    <div class="flex items-center gap-3 flex-wrap">
                        <x-boton-confirmar
                            :action="route('rrhh.papeletas.posthoc-aprobar', $papeleta)"
                            label="Aprobar revisión"
                            color="green"
                            confirmText="¿Aprobar la revisión post-hoc de la papeleta de {{ $papeleta->trabajador->nombre_completo }}?"
                        />
                        <x-accion-comentario :action="route('rrhh.papeletas.posthoc-observar', $papeleta)" label="Observar revisión" color="orange" />
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

            @include('papeletas._historial', ['papeleta' => $papeleta])
        </div>
    </div>
</x-app-layout>
