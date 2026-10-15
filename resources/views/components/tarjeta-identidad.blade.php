@props(['user'])

{{--
    Cabecera de identidad: minimalista, en una sola línea. Avatar +
    nombre + correo a la izquierda, etiquetas a la derecha. Sin banda
    de color, sin tarjeta: solo un borde inferior que la separa del
    resto del contenido.
    El $slot (opcional) recibe acciones o badges extra junto a las etiquetas.
--}}
<div {{ $attributes->class('flex flex-col gap-4 border-b border-tinta-100 pb-6 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between') }}>

    <div class="flex min-w-0 items-center gap-4">
        <x-avatar :user="$user" size="size-16"
                  class="!text-xl ring-1 ring-tinta-100 dark:ring-white/10" />

        <div class="min-w-0">
            <h1 class="truncate font-display text-xl font-bold tracking-tight text-tinta-950 dark:text-white">
                {{ $user->nombre_completo }}
            </h1>
            <p class="mt-0.5 truncate text-sm text-tinta-500 dark:text-zinc-400">
                {{ $user->email }}
            </p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
        <x-tipo-usuario :user="$user" />

        @if ($user->sede)
            <x-etiqueta tono="sello">{{ $user->sede->nombre }}</x-etiqueta>
        @endif

        {{ $slot }}
    </div>
</div>
