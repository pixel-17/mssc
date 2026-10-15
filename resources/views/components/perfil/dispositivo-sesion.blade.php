@props(['sesion'])

{{-- Una fila de "sesión de navegador" (objeto de Jetstream::sessions): icono, SO/navegador, IP y estado. --}}
<div {{ $attributes->class('flex items-center gap-4 rounded-xl border border-tinta-100 px-4 py-3 dark:border-white/10') }}>
    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-tinta-50 text-tinta-600 dark:bg-white/10 dark:text-tinta-100">
        @if ($sesion->agent->isDesktop())
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
        @endif
    </span>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-tinta-950 dark:text-white">
            {{ $sesion->agent->platform() ?: __('Unknown') }} · {{ $sesion->agent->browser() ?: __('Unknown') }}
        </p>
        <p class="truncate text-xs text-tinta-600 dark:text-zinc-400">
            {{ $sesion->ip_address }}
            @unless ($sesion->is_current_device)
                · {{ __('Last active') }} {{ $sesion->last_active }}
            @endunless
        </p>
    </div>

    @if ($sesion->is_current_device)
        <x-etiqueta tono="registro" punto>{{ __('This device') }}</x-etiqueta>
    @endif
</div>
