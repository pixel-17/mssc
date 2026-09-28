{{--
    Acción "Editar" de fila. Reemplaza tanto los enlaces subrayados sin
    affordance de botón (Motivos, Configuraciones, Usuarios) como los
    .btn-row ya correctos pero sin ícono (Turnos, Feriados, Unidades
    orgánicas) — un solo look en los 7 catálogos.
--}}

<a {{ $attributes->merge(['class' => 'btn-row']) }}>
    <x-icon name="pencil" class="size-3.5" />
    {{ $slot->isEmpty() ? 'Editar' : $slot }}
</a>
