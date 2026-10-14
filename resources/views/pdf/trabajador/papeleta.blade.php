@extends('pdf.trabajador.layout', ['titulo' => 'Papeleta'])

@section('contenido')
    <h1>Papeleta N.° {{ str_pad($papeleta->id, 4, '0', STR_PAD_LEFT) }}</h1>
    <div class="sub">{{ $papeleta->trabajador->nombre_completo }}</div>

    <table>
        <tr><th style="width:35%">Motivo</th><td>{{ $papeleta->motivo->nombre }}</td></tr>
        <tr><th>Estado</th><td>{{ \App\Support\PapeletaEstadoPresentacion::para($papeleta->estado)[0] }}</td></tr>
        <tr><th>Sede</th><td>{{ $papeleta->sede->nombre ?? '—' }}</td></tr>
        <tr><th>Día operativo</th><td>{{ $papeleta->dia_operativo?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><th>Hora de salida real</th><td>{{ $papeleta->hora_salida_real?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        <tr><th>Retorno estimado</th><td>{{ $papeleta->hora_retorno_estimado?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        @if ($papeleta->justificacion)
            <tr><th>Justificación</th><td>{{ $papeleta->justificacion }}</td></tr>
        @endif
        @if ($papeleta->retorno)
            <tr>
                <th>Retorno / radio de sede</th>
                <td>
                    @if (is_null($papeleta->retorno->dentro_de_radio))
                        <span class="nd">Sin ubicación</span>
                    @elseif ($papeleta->retorno->dentro_de_radio)
                        <span class="dentro">Dentro del radio</span>
                    @else
                        <span class="fuera">Fuera del radio</span>
                    @endif
                </td>
            </tr>
        @endif
    </table>

    @include('pdf.trabajador._validacion', ['papeleta' => $papeleta])
@endsection
