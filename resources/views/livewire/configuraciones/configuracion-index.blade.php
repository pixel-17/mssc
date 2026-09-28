<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Configuraciones" />

        <x-admin.mensajes />

        <x-admin.tabla :columnas="['Clave', 'Valor', 'Descripción', '']">
            @foreach ($configuraciones as $configuracion)
                <tr wire:key="config-{{ $configuracion->id }}">
                    <td class="px-4 py-3 font-semibold">{{ $configuracion->clave }}</td>
                    <td class="px-4 py-3">{{ $configuracion->valor }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $configuracion->descripcion }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <x-admin.accion-editar :href="route('configuraciones.editar', $configuracion)" />
                    </td>
                </tr>
            @endforeach
        </x-admin.tabla>
    </div>
</div>
