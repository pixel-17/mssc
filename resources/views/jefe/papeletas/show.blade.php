@php
    use App\States\Papeleta\ObservadaPorJefe;
    use App\States\Papeleta\PendienteJefe;
    use App\States\Papeleta\ObservadaPorRrhh;
    use App\States\Papeleta\AutorizadaYCorriendo;

    $estaPendiente = $papeleta->estado->equals(PendienteJefe::class);
    $estaObservada = $papeleta->estado->equals(ObservadaPorJefe::class);

    // Un trabajador puede tener varios jefes inmediatos.
    // Centralizamos esta comprobación para que todos los botones
    // respeten la misma regla de autorización.
    $esJefeInmediato = $papeleta->tieneComoJefeInmediatoA(auth()->user());

    // Tras observar, mientras el trabajador no responda el jefe solo puede rechazar
    // (al responder, la papeleta vuelve a PENDIENTE_JEFE y decide como siempre).
    $estaObservadaPorRrhh = $papeleta->estado->equals(ObservadaPorRrhh::class);

    // Pendiente, observada por el jefe, o observada por RRHH: en los tres casos el jefe decide.
    $puedeDecidir = ($estaPendiente || $estaObservada || $estaObservadaPorRrhh) && $esJefeInmediato;

    $enCurso = $papeleta->estado->equals(AutorizadaYCorriendo::class) && $esJefeInmediato;

    // Observación post-hoc de RRHH: solo responde el MISMO jefe que autorizó.
    $puedeResponderPosthoc = $papeleta->puedeResponderPosthoc(auth()->user());
    $topePosthoc = (int) \App\Models\Configuracion::valorDe('TOPE_OBSERVACIONES_RRHH', 3);

    // El tope de observaciones es configurable (Configuraciones > TOPE_OBSERVACIONES,
    // por defecto 3) y ObservarJefeAction rechaza automáticamente la papeleta en
    // cuanto el contador lo alcanza. Antes este número estaba hardcodeado en la
    // vista como "/3" y el jefe no tenía ninguna advertencia de que "Observar"
    // podía terminar rechazando la papeleta en vez de observarla.
    $topeObservaciones = (int) \App\Models\Configuracion::valorDe('TOPE_OBSERVACIONES', 3);
    $observarAgotaElTope = $papeleta->contador_observaciones_jefe >= $topeObservaciones - 1;
    $avisoObservar = $observarAgotaElTope
        ? "Esta papeleta ya tiene {$papeleta->contador_observaciones_jefe}/{$topeObservaciones} observaciones. Si la observas, alcanzará el tope y el sistema la rechazará automáticamente en vez de esperar respuesta del trabajador."
        : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
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

            @if ($puedeDecidir)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Decisión</h3>

                    @if ($estaObservadaPorRrhh)
                        <p class="text-sm text-gray-600 dark:text-tinta-100/60 mb-3">
                            RRHH observó esta papeleta. Decide directamente: aprobar, observar o rechazar.
                        </p>
                    @endif

                    @if ($estaObservada)
                        <p class="text-sm text-gray-600 dark:text-tinta-100/60 mb-3">
                            Observaste esta papeleta. Espera la respuesta escrita del trabajador{{ $papeleta->observacion_requiere_adjunto ? ' (con archivo adjunto)' : '' }}:
                            volverá a tu bandeja para que decidas. Mientras tanto solo puedes rechazarla.
                        </p>
                    @endif

                    <div class="flex items-center gap-3 flex-wrap">
                        @if ($estaPendiente || $estaObservadaPorRrhh)
                            <form method="POST" action="{{ route('jefe.papeletas.aprobar', $papeleta) }}" x-data="{ enviando: false }" @submit="enviando = true">
                                @csrf
                                <button type="submit" :disabled="enviando" :class="{ 'opacity-50 cursor-not-allowed': enviando }" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md text-white bg-green-600 hover:bg-green-700">
                                    <span x-show="! enviando">Aprobar</span>
                                    <span x-show="enviando" x-cloak>Aprobando…</span>
                                </button>
                            </form>
                        @endif
                        @if ($estaPendiente || $estaObservadaPorRrhh)
                            <x-accion-comentario :action="route('jefe.papeletas.observar', $papeleta)" label="Observar" color="orange" opcion="requiere_adjunto" opcionLabel="Solicitar justificación (el trabajador debe adjuntar un documento)" :opcionMarcada="false" :aviso="$avisoObservar" />
                        @endif
                        <x-accion-comentario :action="route('jefe.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" />
                    </div>
                    @if ($papeleta->contador_observaciones_jefe > 0)
                        <p class="text-xs text-gray-500 dark:text-tinta-100/60 mt-2">Observaciones previas del jefe: {{ $papeleta->contador_observaciones_jefe }}/{{ $topeObservaciones }}</p>
                    @endif
                </div>
            @endif

            @if ($puedeResponderPosthoc)
                <div class="glass-card p-6" x-data="{ enviando: false }">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-1">RRHH observó tu autorización</h3>
                    <p class="text-sm text-gray-600 dark:text-tinta-100/60 mb-3">
                        Autorizaste esta papeleta fuera del horario de RRHH y RRHH dejó una observación
                        ({{ $papeleta->contador_observaciones_posthoc }}/{{ $topePosthoc }}). Responde por escrito y, si quieres, adjunta un sustento: volverá a RRHH para su revisión.
                    </p>
                    <blockquote class="mb-4 border-l-4 border-orange-400 pl-3 text-sm text-gray-700 dark:text-tinta-100/80">{{ $papeleta->posthoc_observacion }}</blockquote>

                    <form method="POST" action="{{ route('jefe.papeletas.responder-posthoc', $papeleta) }}" enctype="multipart/form-data" class="space-y-3" @submit="enviando = true">
                        @csrf
                        <div>
                            <label for="respuesta-posthoc" class="block text-sm font-medium text-gray-700 dark:text-tinta-50/80 mb-1">Tu respuesta</label>
                            <textarea id="respuesta-posthoc" name="respuesta" rows="4" required minlength="5" maxlength="2000"
                                      placeholder="Explica por qué autorizaste la salida (mínimo 5 caracteres)..."
                                      class="block w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500">{{ old('respuesta') }}</textarea>
                            @error('respuesta') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="archivo-posthoc" class="block text-sm font-medium text-gray-700 dark:text-tinta-50/80 mb-1">Sustento (opcional)</label>
                            <input id="archivo-posthoc" type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-gray-600 dark:text-tinta-100/70">
                            <p class="mt-1 text-xs text-gray-500">PDF, JPG o PNG, hasta 10 MB.</p>
                            @error('archivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" :disabled="enviando" :class="{ 'opacity-50 cursor-not-allowed': enviando }"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md text-white bg-orange-500 hover:bg-orange-600">
                            <span x-show="! enviando">Enviar respuesta a RRHH</span>
                            <span x-show="enviando" x-cloak>Enviando…</span>
                        </button>
                    </form>
                </div>
            @endif

            @if ($enCurso && ! $papeleta->retorno)
                <div class="glass-card p-6 space-y-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Papeleta en curso</h3>

                    <div>
                        <p class="text-xs text-gray-500 dark:text-tinta-100/50 mb-2">Retorno manual (solo ante falla de conectividad del trabajador):</p>
                        <x-accion-comentario :action="route('jefe.papeletas.retorno-manual', $papeleta)" label="Marcar retorno manual" color="gray" field="justificacion" :minlength="10" placeholder="Justifica la falla de conectividad (mínimo 10 caracteres)..." confirmText="¿Confirmas el retorno manual por falla de conectividad?" />
                    </div>
                </div>
            @endif

            @include('papeletas._historial', ['papeleta' => $papeleta])
        </div>
    </div>
</x-app-layout>