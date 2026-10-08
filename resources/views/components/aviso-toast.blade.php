@props(['tipo' => 'success'])

{{--
    ÚNICO aviso de la aplicación: tarjeta flotante centrada arriba que se
    oculta sola a los 3 segundos (o al cerrarla).
    - Cargas de página: lo pinta layouts/partials/flash.blade.php (sesión).
    - Actualizaciones de Livewire sin redirigir: lo pintan x-admin.mensajes y
      las vistas con propiedades $mensaje / $mensajeError.
    No usar otros banners fijos de éxito/error: quedarían duplicados.
--}}
@php
    $estilos = [
        'error' => ['caja' => 'bg-red-50 border-red-200 dark:bg-red-950 dark:border-red-500/30', 'texto' => 'text-red-800 dark:text-red-200', 'icono' => 'text-red-500', 'rol' => 'alert', 'path' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z'],
        'success' => ['caja' => 'bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-500/30', 'texto' => 'text-green-800 dark:text-green-200', 'icono' => 'text-green-600', 'rol' => 'status', 'path' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'warning' => ['caja' => 'bg-yellow-50 border-yellow-200 dark:bg-yellow-950 dark:border-yellow-500/30', 'texto' => 'text-yellow-800 dark:text-yellow-200', 'icono' => 'text-yellow-600', 'rol' => 'status', 'path' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
        'info' => ['caja' => 'bg-blue-50 border-blue-200 dark:bg-blue-950 dark:border-blue-500/30', 'texto' => 'text-blue-800 dark:text-blue-200', 'icono' => 'text-blue-500', 'rol' => 'status', 'path' => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z'],
    ][$tipo] ?? null;
    $estilos ??= [
        'caja' => 'bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-500/30', 'texto' => 'text-green-800 dark:text-green-200',
        'icono' => 'text-green-600', 'rol' => 'status', 'path' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
@endphp

<div
    wire:key="aviso-{{ $tipo }}-{{ md5((string) $slot) }}"
    x-data="{ visible: true }"
    x-init="setTimeout(() => visible = false, 3000)"
    x-show="visible" x-cloak
    x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    role="{{ $estilos['rol'] }}"
    class="fixed top-6 left-1/2 -translate-x-1/2 z-[60] w-[calc(100%-2rem)] max-w-md"
>
    <div class="flex items-start gap-3 rounded-xl border shadow-lg px-4 py-3 {{ $estilos['caja'] }}">
        <svg class="size-5 shrink-0 mt-0.5 {{ $estilos['icono'] }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $estilos['path'] }}" /></svg>

        <p class="flex-1 text-sm font-medium {{ $estilos['texto'] }}">{{ $slot }}</p>

        <button type="button" x-on:click="visible = false" class="shrink-0 -my-1 -mr-1 p-1 rounded-md {{ $estilos['texto'] }} opacity-70 hover:opacity-100" aria-label="Cerrar aviso">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
</div>
