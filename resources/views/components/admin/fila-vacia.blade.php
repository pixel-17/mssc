@props(['colspan'])

{{--
    Fila @empty de <x-admin.tabla>. Antes era solo texto centrado; se le
    suma el ícono para que la tabla vacía no se sienta como un error de
    carga sino como un estado normal ("todavía no hay nada aquí").
--}}

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
        <x-icon name="inbox-empty" class="size-8 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
        {{ $slot }}
    </td>
</tr>
