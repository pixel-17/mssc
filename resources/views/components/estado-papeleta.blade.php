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
    PendienteJefe::class            => ['Pendiente Jefe',        'bg-amber-50 text-amber-800 ring-amber-300', 'bg-amber-500'],
    ObservadaPorJefe::class         => ['Observada por Jefe',    'bg-orange-50 text-orange-800 ring-orange-300', 'bg-orange-500'],
    PendienteRrhh::class            => ['Pendiente RRHH',        'bg-amber-50 text-amber-800 ring-amber-300', 'bg-amber-500'],
    ObservadaPorRrhh::class         => ['Observada por RRHH',    'bg-orange-50 text-orange-800 ring-orange-300', 'bg-orange-500'],
    Rechazada::class                => ['Rechazada',             'bg-red-50 text-red-800 ring-red-300', 'bg-red-500'],
    AutorizadaYCorriendo::class     => ['Autorizada / En curso', 'bg-ocean-50 text-ocean-800 ring-ocean-300', 'bg-ocean-500'],
    RetornoPendienteSustento::class => ['Retorno: falta sustento','bg-purple-50 text-purple-800 ring-purple-300', 'bg-purple-500'],
    FinalizadoSinRetorno::class     => ['Sin retorno (abandono)','bg-red-50 text-red-800 ring-red-300', 'bg-red-500'],
    Cerrada::class                  => ['Cerrada',                'bg-emerald-50 text-emerald-800 ring-emerald-300', 'bg-emerald-500'],
    Vencida::class                  => ['Vencida',                'bg-gray-100 text-gray-700 ring-gray-300', 'bg-gray-400'],
    Cancelada::class                => ['Cancelada',              'bg-gray-100 text-gray-700 ring-gray-300', 'bg-gray-400'],
    ReclasificadoAParticular::class => ['Reclasificada a Particular', 'bg-purple-50 text-purple-800 ring-purple-300', 'bg-purple-500'],
];

$clave = $estado instanceof \Spatie\ModelStates\State ? get_class($estado) : (string) $estado;
[$etiqueta, $colores, $punto] = $mapa[$clave] ?? [class_basename($clave), 'bg-gray-100 text-gray-800 ring-gray-300', 'bg-gray-400'];
@endphp

<span {{ $attributes->merge(['class' => "badge-ocean $colores"]) }}>
    <span class="size-1.5 rounded-full {{ $punto }}"></span>
    {{ $etiqueta }}
</span>