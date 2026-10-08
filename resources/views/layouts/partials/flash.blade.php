{{--
    Mensajes de sesión (controllers y Livewire con redirect) como el único aviso de la app:
    tarjeta flotante centrada arriba, se oculta sola a los 3 segundos.
    Muestra un mensaje a la vez: error > éxito > aviso > info.
    Está en layouts.app y en x-trabajador-layout: las vistas NO deben pintar sus propios banners de sesión.
--}}
@php
    $flash = collect([
        ['tipo' => 'error', 'texto' => session('error')],
        ['tipo' => 'success', 'texto' => session('status') ?? session('success') ?? session('mensaje')],
        ['tipo' => 'warning', 'texto' => session('warning')],
        ['tipo' => 'info', 'texto' => session('info')],
    ])->first(fn ($m) => filled($m['texto']));
@endphp

@if ($flash)
    <x-aviso-toast :tipo="$flash['tipo']">{{ $flash['texto'] }}</x-aviso-toast>
@endif
