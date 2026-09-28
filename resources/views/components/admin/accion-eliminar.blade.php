{{--
    Acción "Eliminar" de fila. wire:click y wire:confirm se pasan como
    cualquier otro atributo desde la vista que la usa (igual que
    <x-danger-button>), por ejemplo:

    <x-admin.accion-eliminar wire:click="eliminar({{ $sede->id }})"
        wire:confirm="¿Eliminar la sede &quot;{{ $sede->nombre }}&quot;?" />
--}}

<button type="button" {{ $attributes->merge(['class' => 'btn-row-danger']) }}>
    <x-icon name="trash" class="size-3.5" />
    {{ $slot->isEmpty() ? 'Eliminar' : $slot }}
</button>
