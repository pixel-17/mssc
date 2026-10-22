{{--
    Acción "Eliminar" de fila. Antes recibía wire:click + wire:confirm,
    pero wire:confirm en Livewire 3 siempre dispara el confirm() nativo
    del navegador (no hay forma documentada de reemplazarlo), así que en
    su lugar se pasan $mensaje y $accion y el propio botón abre el modal
    de layouts/partials/confirmar.blade.php antes de invocar la acción
    por $wire, por ejemplo:

    <x-admin.accion-eliminar
        mensaje='¿Eliminar la sede "{{ $sede->nombre }}"?'
        accion="eliminar({{ $sede->id }})" />
--}}

@props(['mensaje', 'accion'])

<button
    type="button"
    x-on:click="mssConfirmar(@js($mensaje), { peligro: true }).then((ok) => ok && $wire.{{ $accion }})"
    {{ $attributes->merge(['class' => 'btn-row-danger']) }}
>
    <x-icon name="trash" class="size-3.5" />
    {{ $slot->isEmpty() ? 'Eliminar' : $slot }}
</button>