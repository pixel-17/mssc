@php
    $observada = $papeleta->estado->equals(\App\States\Papeleta\ObservadaPorJefe::class);
    $puedeSubsanar = $observada && $papeleta->trabajador_id === auth()->id();
    $exigeAdjunto = $puedeSubsanar && $papeleta->observacion_requiere_adjunto;
    $ultimaObservacion = $observada
        ? $papeleta->historial->where('estado_nuevo', 'ObservadaPorJefe')->sortByDesc('id')->first()
        : null;

    // Misma regla que PapeletaPolicy::cancelar (antes de Autorizada y corriendo;
    // en fase RRHH solo con RRHH en horario).
    $puedeCancelar = auth()->user()->can('cancelar', $papeleta);
    $puedeMarcarRetorno = $papeleta->trabajador_id === auth()->id() && $papeleta->estado->equals(\App\States\Papeleta\AutorizadaYCorriendo::class) && ! $papeleta->retorno;
    $sustentoPendiente = $papeleta->estado->equals(\App\States\Papeleta\EnJustificacion::class)
        ? $papeleta->sustentos->firstWhere('estado', 'pendiente')
        : null;

    // El PDF solo certifica una papeleta que terminó aprobada: Cerrada (sin
    // descuento) o Finalizada (con descuento). Rechazada, Cancelada y Vencida
    // también son estados terminales, pero no hay nada que certificar ahí.
    $puedeDescargarPdf = $papeleta->estado->equals(\App\States\Papeleta\Cerrada::class)
        || $papeleta->estado->equals(\App\States\Papeleta\Finalizada::class);

    // Detalle agrupado: qué pasó (información) y quién decidió y cuándo (aprobación).
    $seccionesDetalle = [
        'Información' => collect([
            ['Sede', $papeleta->sede->nombre ?? '—'],
            ['Día operativo', $papeleta->dia_operativo?->format('d/m/Y') ?? '—'],
            ['Hora de salida real', $papeleta->hora_salida_real?->format('d/m/Y H:i') ?? '—'],
            ['Retorno estimado', $papeleta->hora_retorno_estimado?->format('d/m/Y H:i') ?? '—'],
            ['Creada el', $papeleta->created_at?->format('d/m/Y H:i') ?? '—'],
        ]),
        'Aprobación' => collect([
            ['Jefe inmediato', $papeleta->jefeInmediato?->nombre_completo ?? '—'],
            ['Decisión del jefe', $papeleta->jefe_resuelto_at
                ? ($papeleta->resueltoPorJefe?->nombre_completo ?? '—').' · '.$papeleta->jefe_resuelto_at->format('d/m/Y H:i')
                : '—'],
            ['Jefe de área', $papeleta->jefeArea?->nombre_completo ?: '—'],
        ]),
    ];

    if ($papeleta->rrhh_resuelto_at) {
        $seccionesDetalle['Aprobación']->push([
            'Decisión de RRHH',
            ($papeleta->resueltoPorRrhh?->nombre_completo ?? '—').' · '.$papeleta->rrhh_resuelto_at->format('d/m/Y H:i'),
        ]);
    }

    if ($papeleta->autorizado_con_rrhh_fuera_horario) {
        $seccionesDetalle['Aprobación']->push(['Revisión posterior de RR. HH.', $papeleta->etiquetaPosthoc()]);
    }
@endphp

<x-trabajador-layout titulo="Detalle de papeleta" :volver-a="route('trabajador.papeletas.index')">
    <div class="space-y-5" data-en-vivo-contenido>
        {{-- Ticket grande --}}
        <div class="glass-card">
            <div class="p-5 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-tinta-400 dark:text-tinta-300/60">N.° {{ str_pad($papeleta->id, 4, '0', STR_PAD_LEFT) }}</p>
                    <p class="font-display font-semibold text-lg text-tinta-950 dark:text-white leading-snug mt-0.5">{{ $papeleta->motivo->nombre }}</p>
                </div>
                <x-estado-papeleta :estado="$papeleta->estado" :abandono="$papeleta->esAbandono()" class="shrink-0 whitespace-nowrap" />
            </div>

            @if ($puedeDescargarPdf)
                <div class="px-5 pb-4 -mt-2">
                    <a href="{{ route('trabajador.papeletas.pdf', $papeleta) }}" class="btn-secondary inline-flex items-center gap-2 text-sm py-2 px-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Descargar PDF
                    </a>
                </div>
            @endif

            <div class="mx-5 border-t border-dashed border-tinta-200/70 dark:border-white/15"></div>

            @if ($papeleta->justificacion)
                <div class="px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50 mb-1">Justificación</p>
                    <p class="text-sm text-tinta-950 dark:text-white/90">{{ $papeleta->justificacion }}</p>
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
                    <p class="text-sm text-red-700 dark:text-red-200">{{ $papeleta->esAbandono() ? 'Abandono: no marcaste tu retorno antes de que terminara tu turno/día' : $papeleta->causa_finalizacion_sin_retorno }}</p>
                </div>
            @endif
        </div>

        {{-- Papeleta observada por el jefe: qué dijo y qué se espera del trabajador --}}
        @if ($observada)
            <div class="glass-card p-5 border border-orange-300/70 dark:border-orange-400/30">
                <h3 class="text-sm font-semibold text-orange-800 dark:text-orange-300 mb-1">Tu jefe observó esta papeleta</h3>

                @if ($ultimaObservacion?->justificacion)
                    <p class="text-sm text-tinta-950 dark:text-white/90 italic mb-3">"{{ $ultimaObservacion->justificacion }}"</p>
                @endif

                @if ($puedeSubsanar)
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50 mb-3">
                        Responde por escrito{{ $exigeAdjunto ? ' y adjunta el archivo que te pide' : '' }} para que tu jefe vuelva a decidir sobre la papeleta.
                        Si ya no la necesitas, puedes cancelarla.
                    </p>
                    <form method="POST" action="{{ route('trabajador.papeletas.subsanar', $papeleta) }}" enctype="multipart/form-data" class="space-y-3"
                          x-data="{ archivo: null }">
                        @csrf

                        <div>
                            <textarea name="respuesta" rows="4" required minlength="5" maxlength="2000" aria-label="Tu respuesta a la observación"
                                      placeholder="Escribe tu respuesta (mínimo 5 caracteres)..."
                                      class="block w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 dark:border-white/15 dark:bg-white/5 dark:text-white">{{ old('respuesta') }}</textarea>
                            @error('respuesta')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($exigeAdjunto)
                            <label for="archivo-respuesta"
                                   class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-tinta-200 dark:border-white/15 bg-white/40 dark:bg-white/5 py-4 text-center cursor-pointer hover:border-tinta-400 dark:hover:border-tinta-400/60 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-tinta-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                                </svg>
                                <span class="text-sm font-medium text-tinta-700 dark:text-tinta-200" x-text="archivo ?? 'Adjuntar archivo (PDF, JPG o PNG)'"></span>
                            </label>
                            <input type="file" id="archivo-respuesta" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required class="sr-only"
                                   @change="archivo = $event.target.files[0]?.name ?? null">
                            @error('archivo')
                                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        @endif

                        <button type="submit" class="btn-primary w-full text-sm py-3">
                            Enviar respuesta
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Salida autorizada / en curso: banner animado con la hora de retorno estimada --}}
        @if ($papeleta->estado->equals(\App\States\Papeleta\AutorizadaYCorriendo::class) && ! $papeleta->retorno)
            <div class="glass-card p-5 relative overflow-hidden border border-tinta-300/60 dark:border-tinta-400/30">
                <div class="flex items-center gap-3">
                    <span class="relative flex size-3 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-tinta-400 opacity-75"></span>
                        <span class="relative inline-flex size-3 rounded-full bg-tinta-500"></span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-tinta-950 dark:text-white">Salida autorizada, en curso</p>
                        <p class="text-xs text-gray-500 dark:text-tinta-100/50">
                            Saliste a las {{ $papeleta->hora_salida_real?->format('H:i') ?? '—' }}
                        </p>
                    </div>
                </div>

                @if ($papeleta->hora_retorno_estimado)
                    <div class="mt-3 pt-3 border-t border-dashed border-tinta-200/70 dark:border-white/15 flex items-center justify-between gap-3">
                        <span class="text-xs text-gray-500 dark:text-tinta-100/50">Retorno estimado</span>
                        <span
                            @class([
                                'text-sm font-semibold',
                                'text-red-600 dark:text-red-400 animate-pulse' => $papeleta->hora_retorno_estimado->isPast(),
                                'text-tinta-700 dark:text-tinta-200' => ! $papeleta->hora_retorno_estimado->isPast(),
                            ])
                        >
                            {{ $papeleta->hora_retorno_estimado->format('H:i') }}
                            @if ($papeleta->hora_retorno_estimado->isPast())
                                · ya pasó la hora estimada
                            @else
                                · en {{ $papeleta->hora_retorno_estimado->diffForHumans(null, true) }}
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        @endif

        {{-- Acciones contextuales --}}
        @if ($puedeCancelar)
            <form method="POST" action="{{ route('trabajador.papeletas.cancelar', $papeleta) }}"
                  x-data
                  x-on:submit.prevent="mssConfirmar('¿Cancelar esta papeleta?', { aceptar: 'Sí, cancelar', cancelar: 'No', peligro: true }).then(ok => { if (ok) $el.submit(); })">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger-glass w-full text-sm py-3">
                    Cancelar papeleta
                </button>
            </form>
        @endif

        @if ($puedeMarcarRetorno)
            <div class="glass-card p-5" x-data="msscRetornoCamara()">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Marcar retorno</h3>

                <form method="POST" action="{{ route('trabajador.papeletas.retorno.store', $papeleta) }}" enctype="multipart/form-data" class="space-y-3" @submit="prepararEnvio($event)">
                    @csrf

                    {{-- Único botón: abre cámara en vivo + ubicación/dirección a la vez. --}}
                    <template x-if="estado === 'inicial'">
                        <button type="button" @click="iniciar()" class="btn-primary w-full text-sm py-3">
                            📷 Tomar foto de retorno
                        </button>
                    </template>

                    {{-- Cámara en vivo: no hay forma de adjuntar desde galería, solo capturar. --}}
                    <template x-if="estado === 'abriendo' || estado === 'en-vivo'">
                        <div class="space-y-3">
                            <div class="relative overflow-hidden rounded-xl bg-black aspect-[3/4]">
                                {{-- x-init se dispara justo cuando Alpine inserta este <video> en el
                                     DOM (al activarse el x-if de arriba), así que $refs.video siempre
                                     existe para conectarCamara(): ya no hace falta adivinar el timing. --}}
                                <video x-ref="video" x-init="conectarCamara()" playsinline autoplay muted class="h-full w-full object-cover"
                                       :class="facingMode === 'user' && '-scale-x-100'"></video>
                                <p x-show="estado === 'abriendo'" class="absolute inset-0 flex items-center justify-center text-xs text-white/80">
                                    Abriendo cámara…
                                </p>
                                <button type="button" x-show="estado === 'en-vivo'" @click="cambiarCamara()"
                                        class="absolute top-2 right-2 rounded-full bg-black/50 text-white text-xs px-3 py-1.5">
                                    🔄 Cambiar cámara
                                </button>

                                {{-- Marca de agua en vivo (estilo Timestamp Camera): mismo texto y
                                     formato que sellarFoto() graba al capturar, así lo que ves acá
                                     es exactamente lo que va a quedar en la foto. --}}
                                <div x-show="estado === 'en-vivo'" x-cloak
                                     class="pointer-events-none absolute inset-x-0 bottom-0 px-3 pt-8 pb-2.5 text-white"
                                     style="background: linear-gradient(to top, rgba(0,0,0,.75), rgba(0,0,0,.35) 55%, rgba(0,0,0,0));">
                                    <p class="text-sm font-bold leading-tight drop-shadow" x-text="'🕒 ' + horaActual"></p>
                                    <p class="text-xs leading-snug drop-shadow line-clamp-2" x-text="'📍 ' + textoUbicacion()"></p>
                                </div>
                            </div>

                            <p class="text-xs text-tinta-700 dark:text-tinta-200" x-show="!ubicacionLista && !ubicacionError">
                                📍 Obteniendo tu ubicación…
                            </p>
                            <p class="text-xs text-emerald-700 dark:text-emerald-400" x-show="ubicacionLista" x-text="'📍 ' + (direccion ?? 'Ubicación capturada')"></p>
                            <p class="text-xs text-red-600 dark:text-red-400" x-show="ubicacionError" x-text="ubicacionError"></p>

                            <button type="button" @click="capturar()" :disabled="estado !== 'en-vivo' || !ubicacionLista"
                                    class="btn-primary w-full text-sm py-3 disabled:opacity-40 disabled:pointer-events-none">
                                <span x-show="ubicacionLista">Capturar foto</span>
                                <span x-show="!ubicacionLista">Esperando ubicación…</span>
                            </button>
                        </div>
                    </template>

                    {{-- Foto ya sellada con dirección, fecha y hora; lista para confirmar. --}}
                    <template x-if="estado === 'capturada'">
                        <div class="space-y-3">
                            <img :src="fotoPreviewUrl" alt="Foto de retorno" class="w-full rounded-xl">

                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" @click="reintentar()" class="btn-secondary text-sm py-2.5">
                                    Volver a tomar
                                </button>
                                <button type="submit" :disabled="enviando" class="btn-primary text-sm py-2.5 disabled:opacity-40 disabled:pointer-events-none">
                                    <span x-show="!enviando">Confirmar retorno</span>
                                    <span x-show="enviando">Enviando…</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <p class="text-xs text-red-600 dark:text-red-400" x-show="camaraError" x-text="camaraError"></p>

                    <canvas x-ref="canvas" class="hidden"></canvas>
                    {{-- Oculto y sin "required": solo se llena vía JS al capturar, nunca es clickeable. --}}
                    <input type="file" x-ref="inputFoto" name="foto" accept="image/*" class="hidden" tabindex="-1" aria-hidden="true">
                    <input type="hidden" name="latitud" :value="lat">
                    <input type="hidden" name="longitud" :value="lng">
                </form>
            </div>
        @endif

        @if ($sustentoPendiente)
            <div class="glass-card p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-1">Justificación pendiente</h3>
                <p class="text-xs text-gray-500 dark:text-tinta-100/50 mb-3">
                    Fecha límite: {{ $sustentoPendiente->fecha_limite?->format('d/m/Y H:i') }}
                    @if ($papeleta->esAbandono())
                        · No marcaste tu retorno: si presentas tu justificación a tiempo, RRHH o tu jefe decidirán si se justifica.
                    @endif
                </p>
                <form method="POST" action="{{ route('trabajador.papeletas.sustento.store', $sustentoPendiente) }}" enctype="multipart/form-data" class="space-y-3"
                      x-data="{ archivo: null }">
                    @csrf
                    <label for="archivo-sustento"
                           class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-tinta-200 dark:border-white/15 bg-white/40 dark:bg-white/5 py-4 text-center cursor-pointer hover:border-tinta-400 dark:hover:border-tinta-400/60 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-tinta-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                        <span class="text-sm font-medium text-tinta-700 dark:text-tinta-200" x-text="archivo ?? 'Adjuntar justificación'"></span>
                    </label>
                    <input type="file" id="archivo-sustento" name="archivo" required class="sr-only"
                           @change="archivo = $event.target.files[0]?.name ?? null">

                    <button type="submit" class="btn-primary w-full text-sm py-3">
                        Presentar justificación
                    </button>
                </form>
            </div>
        @endif

        {{-- Detalle agrupado --}}
        @foreach ($seccionesDetalle as $titulo => $filas)
            <section>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-100/50 mb-2 px-1">{{ $titulo }}</h3>
                <div class="glass-card divide-y divide-tinta-100/70 dark:divide-white/10">
                    @foreach ($filas as [$etiqueta, $valor])
                        <div class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                            <span class="text-gray-500 dark:text-tinta-100/50">{{ $etiqueta }}</span>
                            <span class="font-medium text-tinta-950 dark:text-white text-right">{{ $valor ?? '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- Retorno registrado --}}
        @if ($papeleta->retorno)
            <div class="glass-card p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">
                    {{ $papeleta->retorno->generado_por_abandono ? 'Hora de fin (sin retorno marcado)' : 'Retorno registrado' }}
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-tinta-100/50">{{ $papeleta->retorno->generado_por_abandono ? 'Hora de fin de turno/día' : 'Hora del servidor' }}</span>
                        <span class="font-medium text-tinta-950 dark:text-white">{{ $papeleta->retorno->hora_servidor->format('d/m/Y H:i') }}</span>
                    </div>
                    @if ($papeleta->retorno->generado_por_abandono)
                        <p class="text-amber-700 dark:text-amber-400">No marcaste tu retorno: esta hora es la de fin de tu turno/día, registrada automáticamente solo para calcular el tiempo que estuviste afuera.</p>
                    @else
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-tinta-100/50">Dentro de radio de sede</span>
                            <span class="font-medium text-tinta-950 dark:text-white">
                                <x-radio-sede :dentro="$papeleta->retorno->dentro_de_radio" />
                            </span>
                        </div>
                    @endif
                    @if ($papeleta->retorno->marcado_manual)
                        <div class="pt-2 border-t border-tinta-100/70 dark:border-white/10">
                            <p class="text-gray-500 dark:text-tinta-100/50">Marcado manual por falla de conectividad</p>
                            <p class="font-medium text-tinta-950 dark:text-white">{{ $papeleta->retorno->marcadoManualPor?->nombre_completo }} — {{ $papeleta->retorno->justificacion_manual }}</p>
                        </div>
                    @endif
                    @if ($papeleta->descuento_refrigerio_minutos)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-tinta-100/50">Descuento de refrigerio</span>
                            <span class="font-medium text-tinta-950 dark:text-white">{{ $papeleta->descuento_refrigerio_minutos }} min</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Sustentos --}}
        @if ($papeleta->sustentos->isNotEmpty())
            <div class="glass-card p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Sustentos</h3>
                <ul class="space-y-2">
                    @foreach ($papeleta->sustentos as $sustento)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-gray-600 dark:text-tinta-100/70">
                                {{ $sustento->presentado_at?->format('d/m/Y H:i') ?? '—' }}
                                · límite {{ $sustento->fecha_limite?->format('d/m/Y H:i') }}
                                @if ($sustento->archivo_path)
                                    · <a href="{{ route('sustentos.archivo', $sustento) }}" class="underline hover:text-tinta-600 dark:hover:text-tinta-300">ver archivo</a>
                                @endif
                            </span>
                            <span class="badge-tinta shrink-0 {{ match($sustento->estado) {
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

        @include('papeletas._archivos', ['papeleta' => $papeleta])

        @include('trabajador.papeletas._timeline', ['papeleta' => $papeleta])
    </div>
</x-trabajador-layout>