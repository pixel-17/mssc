@props(['id', 'nombre' => '', 'puedeEditar' => false, 'puedeMover' => false])

{{--
    Menú «⋯» de una persona en el árbol. Siempre visible (antes los íconos solo
    aparecían en modo edición y con poca opacidad). Es el mismo menú para jefes y
    trabajadores, para admin y jefe de área:
      - Ver ficha        → panel lateral con sus datos.
      - Editar datos     → drawer de edición (mismo que «Agregar persona»).
      - Mover a otra unidad → solo en modo edición; abre la ficha en el selector de destino.
    data-menu-abierto sube la tarjeta por encima de sus vecinas mientras el menú está abierto
    (ver .org-tree li:has(...) en organigrama-arbol).
--}}
<div x-data="{ o: false }" @click.outside="o = false" @keydown.escape="if (o) { $event.stopPropagation(); o = false }"
     :data-menu-abierto="o ? 'true' : null" class="relative ms-auto">
    <button type="button" @click="o = ! o" :aria-expanded="o" aria-haspopup="menu"
            class="rounded-lg p-1 text-gray-500 hover:bg-white hover:text-tinta-700 focus-visible:ring-2 focus-visible:ring-tinta-500 dark:text-tinta-100 dark:hover:bg-white/10">
        <svg class="size-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="19" cy="12" r="1.7" /></svg>
        <span class="sr-only">Acciones de {{ $nombre }}</span>
    </button>

    <div x-show="o" x-cloak x-transition.opacity.duration.100ms role="menu"
         class="absolute right-0 z-30 mt-1 w-52 rounded-xl border border-gray-200 bg-white p-1 text-left shadow-lg dark:border-white/15 dark:bg-gray-800">
        <button type="button" role="menuitem" @click="o = false; $wire.verPersona({{ $id }})"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-tinta-950 hover:bg-tinta-50 dark:text-white dark:hover:bg-white/10">
            <x-icon name="user-circle" class="size-4" /> Ver ficha
        </button>
        @if ($puedeEditar)
            <button type="button" role="menuitem" @click="o = false; $dispatch('org-persona-abrir', { id: {{ $id }} })"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-tinta-950 hover:bg-tinta-50 dark:text-white dark:hover:bg-white/10">
                <x-icon name="pencil" class="size-4" /> Editar datos
            </button>
        @endif
        @if ($puedeMover)
            <button type="button" role="menuitem" @click="o = false; $wire.verPersona({{ $id }}, true)"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-tinta-950 hover:bg-tinta-50 dark:text-white dark:hover:bg-white/10">
                <x-icon name="share" class="size-4" /> Mover a otra unidad…
            </button>
        @endif
    </div>
</div>
