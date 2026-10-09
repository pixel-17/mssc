@props(['label', 'for', 'ayuda' => null, 'campo' => null])

{{--
    Envoltorio label + control + error para los formularios del catálogo
    admin. El control (x-input, x-select, textarea...) va en el slot —
    igual que <x-admin.filtro-campo>, pero este además pinta la ayuda y
    el error de validación, que un filtro no necesita.

    Antes de esto, 6 de los 7 formularios (todos menos Sedes) no usaban
    <x-label>/<x-input>/<x-input-error> y tenían su propio <label> +
    <input class="rounded-md border-gray-300 dark:bg-gray-800"> suelto,
    sin el estilo (borde tinta, foco tinta-500) que el resto del sistema
    ya usa.
--}}

<div>
    <x-label :for="$for" :value="$label" class="block mb-1" />

    {{ $slot }}

    @if ($ayuda)
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $ayuda }}</p>
    @endif

    {{-- El error se busca por la propiedad Livewire (campo); si no se indica, por for. --}}
    <x-input-error :for="$campo ?? $for" class="mt-2" />
</div>