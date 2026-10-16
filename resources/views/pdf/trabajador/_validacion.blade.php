@php
    $aprobo = collect([
        ['Jefe', $papeleta->resueltoPorJefe?->nombre_completo],
        ['RR. HH.', $papeleta->resueltoPorRrhh?->nombre_completo],
    ])->filter(fn ($f) => $f[1]);
@endphp
<div class="validacion">
    <div class="t">Validado por el sistema</div>
    <div>
        <strong>Aprobó:</strong>
        @if ($aprobo->isEmpty())
            —
        @else
            {{ $aprobo->map(fn ($f) => "{$f[1]} ({$f[0]})")->implode(' · ') }}
        @endif
    </div>
    <div><strong>Hora de solicitud:</strong> {{ $papeleta->created_at->format('d/m/Y H:i') }}</div>
    <div><strong>{{ $papeleta->retorno?->generado_por_abandono ? 'Hora de fin (sin retorno marcado)' : 'Hora de retorno' }}:</strong> {{ $papeleta->retorno?->hora_servidor?->format('d/m/Y H:i') ?? '—' }}</div>
</div>
