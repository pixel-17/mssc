<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        @if ($papeleta->relationLoaded('trabajador') && $papeleta->trabajador)
            <div>
                <dt class="text-gray-500">Trabajador</dt>
                <dd class="text-gray-900">{{ $papeleta->trabajador->nombre_completo }} ({{ $papeleta->regimen }})</dd>
            </div>
        @endif
        <div>
            <dt class="text-gray-500">Sede</dt>
            <dd class="text-gray-900">{{ $papeleta->sede->nombre ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Día operativo</dt>
            <dd class="text-gray-900">{{ $papeleta->dia_operativo?->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Jefe inmediato</dt>
            <dd class="text-gray-900">{{ $papeleta->jefeInmediato?->nombre_completo ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Jefe de área</dt>
            <dd class="text-gray-900">{{ $papeleta->jefeArea?->nombre_completo ?? '—' }}{{ $papeleta->escalado_jefe_area_at ? ' (escalado)' : '' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Hora de salida real</dt>
            <dd class="text-gray-900">{{ $papeleta->hora_salida_real?->format('d/m/Y H:i') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Es emergencia</dt>
            <dd class="text-gray-900">{{ $papeleta->es_emergencia ? 'Sí' : 'No' }}</dd>
        </div>
        @if ($papeleta->autorizado_con_rrhh_fuera_horario)
            <div>
                <dt class="text-gray-500">Revisión post-hoc RRHH</dt>
                <dd class="text-gray-900">{{ ucfirst($papeleta->revision_posthoc_estado ?? 'pendiente') }}</dd>
            </div>
        @endif
        @if ($papeleta->justificacion)
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Justificación</dt>
                <dd class="text-gray-900">{{ $papeleta->justificacion }}</dd>
            </div>
        @endif
        @if ($papeleta->motivo_rechazo)
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Motivo de rechazo</dt>
                <dd class="text-red-700">{{ $papeleta->motivo_rechazo }}</dd>
            </div>
        @endif
        @if ($papeleta->causa_finalizacion_sin_retorno)
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Causa de finalización sin retorno</dt>
                <dd class="text-red-700">{{ $papeleta->causa_finalizacion_sin_retorno }}</dd>
            </div>
        @endif
    </dl>
</div>

@if ($papeleta->retorno)
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Retorno registrado</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Hora del servidor</dt>
                <dd class="text-gray-900">{{ $papeleta->retorno->hora_servidor->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Dentro de radio de sede</dt>
                <dd class="text-gray-900">
                    @if(is_null($papeleta->retorno->dentro_de_radio)) — @else {{ $papeleta->retorno->dentro_de_radio ? 'Sí' : 'No' }} @endif
                </dd>
            </div>
            @if ($papeleta->retorno->marcado_manual)
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Marcado manual por falla de conectividad</dt>
                    <dd class="text-gray-900">{{ $papeleta->retorno->marcadoManualPor?->nombre_completo }} — {{ $papeleta->retorno->justificacion_manual }}</dd>
                </div>
            @endif
            @if ($papeleta->descuento_refrigerio_minutos)
                <div>
                    <dt class="text-gray-500">Descuento de refrigerio</dt>
                    <dd class="text-gray-900">{{ $papeleta->descuento_refrigerio_minutos }} min</dd>
                </div>
            @endif
        </dl>
    </div>
@endif

@if ($papeleta->sustentos->isNotEmpty())
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Sustentos</h3>
        <ul class="text-sm text-gray-700 space-y-1">
            @foreach ($papeleta->sustentos as $sustento)
                <li class="flex items-center justify-between">
                    <span>
                        Presentado {{ $sustento->presentado_at?->format('d/m/Y H:i') ?? '—' }}
                        · Límite {{ $sustento->fecha_limite?->format('d/m/Y H:i') }}
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $sustento->estado === 'aprobado' ? 'bg-green-100 text-green-800' : ($sustento->estado === 'observado' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-600') }}">
                        {{ ucfirst($sustento->estado) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
