{{--
    Mensajes de las pantallas de administración cuando una acción de Livewire
    NO redirige (eliminar, desactivar, reactivar…). En una carga completa de
    página (p. ej. tras guardar un formulario y redirigir) el aviso ya lo
    pinta layouts/partials/flash, por eso aquí solo se muestra durante una
    actualización de Livewire: así nunca salen dos avisos.
    Las acciones sin redirect deben usar session()->now(), no flash().
--}}
@if (request()->hasHeader('X-Livewire'))
    @if (session('error'))
        <x-aviso-toast tipo="error">{{ session('error') }}</x-aviso-toast>
    @elseif (session('mensaje'))
        <x-aviso-toast tipo="success">{{ session('mensaje') }}</x-aviso-toast>
    @endif
@endif
