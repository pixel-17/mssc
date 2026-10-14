@extends('pdf.trabajador.layout', ['titulo' => 'Mis papeletas'])

@section('contenido')
    <h1>Mis papeletas</h1>
    <div class="sub">{{ $trabajador->nombre_completo }} · {{ $todas ? 'Todas' : 'Turno vigente' }}</div>

    @forelse ($papeletas as $papeleta)
        <table>
            <tr>
                <th style="width:12%">N.° {{ str_pad($papeleta->id, 4, '0', STR_PAD_LEFT) }}</th>
                <th>{{ $papeleta->motivo->nombre }} · {{ $papeleta->dia_operativo?->format('d/m/Y') ?? '—' }}
                    · {{ \App\Support\PapeletaEstadoPresentacion::para($papeleta->estado)[0] }}</th>
            </tr>
            <tr>
                <td colspan="2">
                    @include('pdf.trabajador._validacion', ['papeleta' => $papeleta])
                </td>
            </tr>
        </table>
    @empty
        <p class="nd">No hay papeletas para mostrar.</p>
    @endforelse
@endsection
