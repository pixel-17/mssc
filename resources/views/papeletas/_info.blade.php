<div class="glass-card p-6">
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        @if ($papeleta->relationLoaded('trabajador') && $papeleta->trabajador)
            <div>
                <dt class="text-gray-500 dark:text-tinta-100/50">Trabajador</dt>
                <dd class="text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }} ({{ $papeleta->regimen }})</dd>
            </div>
        @endif
        <div>
            <dt class="text-gray-500 dark:text-tinta-100/50">Sede</dt>
            <dd class="text-gray-900 dark:text-white">{{ $papeleta->sede->nombre ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-tinta-100/50">Día operativo</dt>
            <dd class="text-gray-900 dark:text-white">{{ $papeleta->dia_operativo?->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-tinta-100/50">Jefe inmediato</dt>
            <dd class="text-gray-900 dark:text-white">{{ $papeleta->jefeInmediato?->nombre_completo ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-tinta-100/50">Jefe de área</dt>
            <dd class="text-gray-900 dark:text-white">{{ $papeleta->jefeArea?->nombre_completo ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-tinta-100/50">Hora de salida real</dt>
            <dd class="text-gray-900 dark:text-white">{{ $papeleta->hora_salida_real?->format('d/m/Y H:i') ?? '—' }}</dd>
        </div>
        @if ($papeleta->autorizado_con_rrhh_fuera_horario)
            <div>
                <dt class="text-gray-500 dark:text-tinta-100/50">Revisión post-hoc RRHH</dt>
                <dd class="text-gray-900 dark:text-white">{{ ucfirst($papeleta->revision_posthoc_estado ?? 'pendiente') }}</dd>
            </div>
        @endif
        @if ($papeleta->justificacion)
            <div class="sm:col-span-2">
                <dt class="text-gray-500 dark:text-tinta-100/50">Justificación</dt>
                <dd class="text-gray-900 dark:text-white">{{ $papeleta->justificacion }}</dd>
            </div>
        @endif
        @if ($papeleta->observacion_subsanada_at)
            <div class="sm:col-span-2">
                <dt class="text-gray-500 dark:text-tinta-100/50">Respuesta del trabajador a la observación ({{ $papeleta->observacion_subsanada_at->format('d/m/Y H:i') }})</dt>
                <dd class="text-gray-900 dark:text-white">{{ $papeleta->observacion_respuesta }}</dd>
            </div>
        @endif
        @if ($papeleta->motivo_rechazo)
            <div class="sm:col-span-2">
                <dt class="text-gray-500 dark:text-tinta-100/50">Motivo de rechazo</dt>
                <dd class="text-red-700 dark:text-red-400">{{ $papeleta->motivo_rechazo }}</dd>
            </div>
        @endif
        @if ($papeleta->causa_finalizacion_sin_retorno)
            <div class="sm:col-span-2">
                <dt class="text-gray-500 dark:text-tinta-100/50">Causa de finalización sin retorno</dt>
                <dd class="text-red-700 dark:text-red-400">{{ $papeleta->causa_finalizacion_sin_retorno }}</dd>
            </div>
        @endif
    </dl>
</div>

@if ($papeleta->retorno)
    <div class="glass-card p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Retorno registrado</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-tinta-100/50">Hora del servidor</dt>
                <dd class="text-gray-900 dark:text-white">{{ $papeleta->retorno->hora_servidor->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-tinta-100/50">Dentro de radio de sede</dt>
                <dd class="text-gray-900 dark:text-white">
                    @if(is_null($papeleta->retorno->dentro_de_radio)) — @else {{ $papeleta->retorno->dentro_de_radio ? 'Sí' : 'No' }} @endif
                </dd>
            </div>
            @if ($papeleta->retorno->marcado_manual)
                <div class="sm:col-span-2">
                    <dt class="text-gray-500 dark:text-tinta-100/50">Marcado manual por falla de conectividad</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $papeleta->retorno->marcadoManualPor?->nombre_completo }} — {{ $papeleta->retorno->justificacion_manual }}</dd>
                </div>
            @endif
            @if ($papeleta->descuento_refrigerio_minutos)
                <div>
                    <dt class="text-gray-500 dark:text-tinta-100/50">Descuento de refrigerio</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $papeleta->descuento_refrigerio_minutos }} min</dd>
                </div>
            @endif
        </dl>
    </div>
@endif

@if ($papeleta->sustentos->isNotEmpty())
    <div class="glass-card p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Sustentos</h3>
        <ul class="text-sm text-gray-700 dark:text-tinta-100/70 space-y-1">
            @foreach ($papeleta->sustentos as $sustento)
                <li class="flex items-center justify-between">
                    <span>
                        Presentado {{ $sustento->presentado_at?->format('d/m/Y H:i') ?? '—' }}
                        · Límite {{ $sustento->fecha_limite?->format('d/m/Y H:i') }}
                        @if ($sustento->archivo_path)
                            · <a href="{{ route('sustentos.archivo', $sustento) }}" target="_blank" rel="noopener" class="text-tinta-600 dark:text-tinta-300 hover:text-tinta-700 dark:hover:text-tinta-100 underline">Ver archivo</a>
                        @endif
                    </span>
                    @php [$etiquetaSustento, $clasesSustento] = \App\Support\SustentoEstadoPresentacion::para($sustento->estado); @endphp
                    <span class="inline-flex items-center text-xs px-2 py-0.5 rounded-full font-semibold ring-1 ring-inset {{ $clasesSustento }}">
                        {{ $etiquetaSustento }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@include('papeletas._archivos', ['papeleta' => $papeleta])
