@props(['codigos' => []])

{{-- Bloque de códigos (p. ej. de recuperación 2FA), en monoespaciada y con modo oscuro. --}}
<div {{ $attributes->class('grid max-w-xl gap-x-6 gap-y-1 rounded-xl border border-tinta-100 bg-tinta-50 px-4 py-4 font-mono text-sm text-tinta-900 sm:grid-cols-2 dark:border-white/10 dark:bg-white/5 dark:text-tinta-50') }}>
    @foreach ($codigos as $codigo)
        <div>{{ $codigo }}</div>
    @endforeach
</div>
