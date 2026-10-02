@props(['colspan'])

{{--
    Fila @empty de <x-admin.tabla>. Antes era solo texto centrado; se le
    suma el ícono para que la tabla vacía no se sienta como un error de
    carga sino como un estado normal ("todavía no hay nada aquí").
--}}

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-sm text-tinta-500 dark:text-tinta-200">
        <x-icon name="inbox-empty" class="size-8 mx-auto mb-2 text-tinta-300 dark:text-tinta-500" />
        {{ $slot }}
    </td>
</tr>
