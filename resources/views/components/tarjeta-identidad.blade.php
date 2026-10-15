@props(['user'])

{{--
    Tarjeta de identidad: banda superior de color, avatar sobre ella,
    nombre completo, correo, tipo de usuario y sede.
    El $slot (opcional) recibe acciones o badges extra a la derecha.
--}}
<div {{ $attributes->class('overflow-hidden rounded-2xl border border-tinta-100 bg-white shadow-sm dark:border-white/10 dark:bg-[var(--surface)]') }}>

    {{-- Banda de color --}}
    <div class="h-24 bg-gradient-to-br from-tinta-900 via-tinta-700 to-tinta-500 dark:from-tinta-950 dark:via-tinta-800 dark:to-tinta-600"></div>

    {{-- Contenido debajo de la banda --}}
    <div class="flex flex-col gap-4 px-6 pb-6 sm:flex-row sm:items-end sm:justify-between">

        {{-- Avatar (sobresale la banda) + datos --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-5">
            <div class="-mt-12 shrink-0">
                <x-avatar :user="$user" size="size-24"
                          class="!text-3xl ring-4 ring-white dark:ring-[var(--surface)]" />
            </div>

            <div class="min-w-0 pb-1">
                <h2 class="truncate font-display text-2xl font-bold leading-tight tracking-tight text-tinta-950 dark:text-white">
                    {{ $user->nombre_completo }}
                </h2>
                <p class="mt-0.5 truncate text-sm text-tinta-500 dark:text-zinc-400">
                    {{ $user->email }}
                </p>
            </div>
        </div>

        {{-- Etiquetas + acciones opcionales --}}
        <div class="flex flex-wrap items-center gap-2 pb-1">
            <x-tipo-usuario :user="$user" />

            @if ($user->sede)
                <x-etiqueta tono="sello">{{ $user->sede->nombre }}</x-etiqueta>
            @endif

            {{ $slot }}
        </div>
    </div>
</div>
