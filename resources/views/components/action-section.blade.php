@props(['peligro' => false])

{{-- Sección de acciones (2FA, sesiones, borrar cuenta…): como form-section pero sin <form>. --}}
<x-seccion :titulo="$title" :descripcion="$description" :peligro="$peligro" {{ $attributes }}>
    <div class="px-5 py-5 sm:px-6 sm:py-6">
        {{ $content }}
    </div>
</x-seccion>
