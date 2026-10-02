@props(['user'])

{{-- Etiqueta de color con el tipo de usuario (ver User::tipoUsuario()). --}}
@php
    $tipo = $user->tipoUsuario();
    $estilos = [
        'admin' => 'bg-violet-100 text-violet-800 ring-violet-200 dark:bg-violet-500/20 dark:text-violet-200 dark:ring-violet-400/30',
        'rrhh' => 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-400/30',
        'jefe_area' => 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-400/30',
        'jefe_inmediato' => 'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-400/30',
        'trabajador' => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-white/10 dark:text-tinta-100 dark:ring-white/15',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset', $estilos[$tipo]]) }}>
    <span class="size-1.5 rounded-full bg-current opacity-70"></span>{{ $user->etiquetaTipoUsuario() }}
</span>
