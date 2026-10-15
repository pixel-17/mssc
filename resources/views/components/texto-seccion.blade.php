{{-- Párrafo explicativo dentro de una sección (mismo tamaño y color en todas). --}}
<div {{ $attributes->class(['max-w-2xl text-sm leading-relaxed text-tinta-600 dark:text-zinc-400']) }}>
    {{ $slot }}
</div>
