@props(['peligro' => false])

{{-- Título + descripción de una sección. Lo usa <x-seccion> en su cabecera. --}}
<div class="min-w-0">
    <h3 @class([
        'font-display text-lg font-semibold tracking-tight',
        'text-tinta-950 dark:text-white' => ! $peligro,
        'text-alarma-700 dark:text-alarma-100' => $peligro,
    ])>{{ $title }}</h3>

    @if (filled($description ?? null))
        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-tinta-600 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
