{{--
    Mensajes de sesión del ciclo request/response. Los avisos que nacen en
    Livewire van por <x-flash-messages />; este parcial cubre el
    session('status') / session('error') de los controllers.
--}}
@if (session('status') || session('success'))
    <div class="alerta alerta-exito" role="status">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5 shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>
        <p>{{ session('status') ?? session('success') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="alerta alerta-error" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5 shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        <p>{{ session('error') }}</p>
    </div>
@endif
