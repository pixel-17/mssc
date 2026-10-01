@props(['model', 'options' => [], 'placeholder' => 'Buscar…', 'vacio' => 'Sin selección', 'limpiable' => true, 'id' => null, 'live' => false])

{{--
    Select con buscador (reemplaza al <select> nativo cuando la lista es larga:
    personas, unidades). Se enlaza a una propiedad Livewire con $wire.$entangle
    (se envía junto con la siguiente acción, igual que wire:model sin .live).

    options: lista de ['id' => int, 'label' => string, 'hint' => ?string].
    Teclado: ↓ ↑ para moverse, Enter para elegir, Esc para cerrar.
--}}
<div x-data="{
        open: false, q: '', i: 0,
        value: $wire.$entangle('{{ $model }}'){{ $live ? '.live' : '' }},
        options: @js($options),
        get actual() { return this.options.find(o => String(o.id) === String(this.value)) },
        get filtradas() {
            const t = this.q.trim().toLowerCase();
            return t === '' ? this.options : this.options.filter(o => (o.label + ' ' + (o.hint || '')).toLowerCase().includes(t));
        },
        abrir() { this.open = true; this.i = 0; this.$nextTick(() => this.$refs.buscar.focus()) },
        cerrar() { this.open = false; this.q = '' },
        elegir(o) { this.value = o ? o.id : null; this.cerrar() },
        init() { this.$watch('i', () => this.$nextTick(() => this.$refs.lista?.querySelector('[data-activo=true]')?.scrollIntoView({ block: 'nearest' }))) },
     }"
     @click.outside="cerrar()"
     @keydown.escape="if (open) { $event.stopPropagation(); cerrar() }"
     class="relative">
    <button type="button" @if ($id) id="{{ $id }}" @endif @click="open ? cerrar() : abrir()"
            :aria-expanded="open" aria-haspopup="listbox"
            class="flex w-full items-center justify-between gap-2 rounded-xl border border-tinta-200 bg-white/70 px-3 py-2 text-left text-sm shadow-sm transition focus:border-tinta-500 focus:outline-none focus:ring-1 focus:ring-tinta-500 dark:border-white/15 dark:bg-white/10 dark:text-white">
        <span class="min-w-0 flex-1 truncate" :class="actual ? '' : 'text-gray-400 dark:text-white/40'" x-text="actual ? actual.label : '{{ $vacio }}'"></span>
        <span class="flex items-center gap-1 text-gray-400">
            @if ($limpiable)
                <span x-show="actual" x-cloak role="button" tabindex="-1" @click.stop="elegir(null)" class="rounded p-0.5 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10" aria-label="Quitar selección">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </span>
            @endif
            <svg class="size-4 transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.origin.top.duration.120ms
         class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-white/15 dark:bg-gray-800">
        <div class="border-b border-gray-100 p-2 dark:border-white/10">
            <input type="text" x-ref="buscar" x-model="q" placeholder="{{ $placeholder }}" autocomplete="off"
                   @input="i = 0"
                   @keydown.arrow-down.prevent="i = Math.min(i + 1, filtradas.length - 1)"
                   @keydown.arrow-up.prevent="i = Math.max(i - 1, 0)"
                   @keydown.enter.prevent="filtradas[i] && elegir(filtradas[i])"
                   class="w-full rounded-lg border-gray-200 bg-gray-50 px-2.5 py-1.5 text-sm focus:border-tinta-500 focus:ring-tinta-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>
        <ul x-ref="lista" role="listbox" class="max-h-56 overflow-y-auto py-1">
            <template x-for="(o, idx) in filtradas" :key="o.id">
                <li role="option" :aria-selected="String(o.id) === String(value)" :data-activo="idx === i"
                    @click="elegir(o)" @mouseenter="i = idx"
                    class="flex cursor-pointer items-center justify-between gap-3 px-3 py-1.5 text-sm"
                    :class="idx === i ? 'bg-tinta-50 dark:bg-white/10' : ''">
                    <span class="min-w-0 truncate text-tinta-950 dark:text-white" :class="String(o.id) === String(value) && 'font-semibold'" x-text="o.label"></span>
                    <span class="shrink-0 truncate text-[11px] text-gray-500 dark:text-tinta-50/60" x-show="o.hint" x-text="o.hint"></span>
                </li>
            </template>
            <li x-show="filtradas.length === 0" class="px-3 py-3 text-center text-xs text-gray-500 dark:text-tinta-50/60">Sin resultados</li>
        </ul>
    </div>
</div>
