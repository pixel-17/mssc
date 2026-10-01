@props(['titulo', 'subtitulo' => null, 'icono' => 'building'])

{{--
    Panel lateral (slide-over) de edición del organigrama. Entra con
    animación, se cierra con Esc, clic fuera o la X, y atrapa el foco.
    Al cerrarse espera la animación de salida y llama a $wire.cerrar() del
    componente Livewire que lo contiene.

    Slots: el contenido va en el slot por defecto; los botones, en `pie`.
--}}
<div x-data="{ show: false, cerrar() { this.show = false; setTimeout(() => this.$wire.cerrar(), 170) } }"
     x-init="$nextTick(() => show = true)"
     @keydown.escape.window="cerrar()"
     class="fixed inset-0 z-50"
     role="dialog" aria-modal="true" aria-label="{{ $titulo }}">
    <div x-show="show" x-transition.opacity.duration.200ms class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="cerrar()" aria-hidden="true"></div>

    <aside x-show="show" x-cloak x-trap.noscroll="show"
           x-transition:enter="transform transition ease-out duration-200"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transform transition ease-in duration-150"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-gray-900">
        <header class="flex items-start gap-3 border-b border-gray-100 px-6 py-4 dark:border-white/10">
            <span class="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-tinta-50 text-tinta-700 dark:bg-white/10 dark:text-tinta-100">
                <x-icon :name="$icono" class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-base font-semibold text-tinta-950 dark:text-white">{{ $titulo }}</h2>
                @if ($subtitulo)
                    <p class="truncate text-xs text-gray-500 dark:text-tinta-50/60">{{ $subtitulo }}</p>
                @endif
            </div>
            <button type="button" @click="cerrar()" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10" aria-label="Cerrar">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
            {{ $slot }}
        </div>

        @isset($pie)
            <footer class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-6 py-3 dark:border-white/10 dark:bg-white/5">
                {{ $pie }}
            </footer>
        @endisset
    </aside>
</div>
