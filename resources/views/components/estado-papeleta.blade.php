@props(['estado'])

@php
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\Rechazada;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\RetornoPendienteSustento;
use App\States\Papeleta\FinalizadoSinRetorno;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\Vencida;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\ReclasificadoAParticular;

$mapa = [
    PendienteJefe::class            => ['Pendiente Jefe',        'bg-yellow-100 text-yellow-800'],
    ObservadaPorJefe::class         => ['Observada por Jefe',    'bg-orange-100 text-orange-800'],
    PendienteRrhh::class            => ['Pendiente RRHH',        'bg-yellow-100 text-yellow-800'],
    ObservadaPorRrhh::class         => ['Observada por RRHH',    'bg-orange-100 text-orange-800'],
    Rechazada::class                => ['Rechazada',             'bg-red-100 text-red-800'],
    AutorizadaYCorriendo::class     => ['Autorizada / En curso', 'bg-blue-100 text-blue-800'],
    RetornoPendienteSustento::class => ['Retorno: falta sustento','bg-purple-100 text-purple-800'],
    FinalizadoSinRetorno::class     => ['Sin retorno (abandono)','bg-red-100 text-red-800'],
    Cerrada::class                  => ['Cerrada',                'bg-green-100 text-green-800'],
    Vencida::class                  => ['Vencida',                'bg-gray-200 text-gray-700'],
    Cancelada::class                => ['Cancelada',              'bg-gray-200 text-gray-700'],
    ReclasificadoAParticular::class => ['Reclasificada a Particular', 'bg-purple-100 text-purple-800'],
];

$clave = $estado instanceof \Spatie\ModelStates\State ? get_class($estado) : (string) $estado;
[$etiqueta, $colores] = $mapa[$clave] ?? [class_basename($clave), 'bg-gray-100 text-gray-800'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium $colores"]) }}>
    {{ $etiqueta }}
</span>