<div
    x-data="{ todos: null }"
    class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6"
>
    {{-- Conectores del árbol. Van aquí (no en app.css) para que funcionen sin recompilar. --}}
    <style>
        .org-tree { --org-line: #cbd5e1; }
        .dark .org-tree { --org-line: rgba(255, 255, 255, .18); }
        .org-tree ul { list-style: none; margin: 0 0 0 .9rem; padding: 0; }
        .org-tree ul > li { position: relative; padding-left: 1.6rem; padding-top: .75rem; }
        .org-tree ul > li::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; border-left: 2px solid var(--org-line); }
        .org-tree ul > li:last-child::before { bottom: auto; height: 2.1rem; }
        .org-tree ul > li::after { content: ''; position: absolute; left: 0; top: 2.1rem; width: 1.3rem; border-top: 2px solid var(--org-line); }
        .org-tree > ul { margin-left: 0; }
        .org-tree > ul > li { padding-left: 0; }
        .org-tree > ul > li::before, .org-tree > ul > li::after { display: none; }
    </style>

    <x-admin.encabezado titulo="Organigrama del área">
        <div class="flex items-center gap-2">
            <button type="button" class="btn-secondary text-xs" @click="todos = true; $dispatch('org-expandir')">Expandir todo</button>
            <button type="button" class="btn-secondary text-xs" @click="todos = false; $dispatch('org-contraer')">Contraer todo</button>
        </div>
    </x-admin.encabezado>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-stat-card label="Unidades" :value="$stats['unidades']" />
        <x-stat-card label="Personas" :value="$stats['personas']" />
        <x-stat-card label="Sin sede" :value="$stats['sin_sede']" :hint="$stats['sin_sede'] ? 'Hay que asignarles sede' : 'Todos tienen sede'" />
        <x-stat-card label="Turnos sin jefe" :value="$stats['turnos_sin_jefe']" hint="Unidades 728" />
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <x-admin.filtro-campo label="Buscar" for="organigrama-buscar">
            <x-input id="organigrama-buscar" type="text" wire:model.live.debounce.400ms="buscar" placeholder="Unidad, jefe, trabajador o DNI" />
        </x-admin.filtro-campo>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-tinta-50/80 pb-2">
            <input type="checkbox" wire:model.live="verInactivos" class="rounded border-gray-300 text-tinta-600 focus:ring-tinta-500">
            Mostrar desactivados
        </label>

        <div class="ms-auto flex flex-wrap items-center gap-3 pb-2 text-xs text-gray-600 dark:text-tinta-50/70">
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-tinta-600"></span> Jefe</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-emerald-500"></span> Trabajador</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-amber-500"></span> Sede distinta a la de su jefe</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-full bg-red-500"></span> Sin sede</span>
        </div>
    </div>

    @if ($raices->isEmpty())
        <div class="glass-card p-8 text-center text-sm text-gray-500 dark:text-tinta-50/70">
            {{ $buscar !== '' ? 'Nada coincide con la búsqueda.' : 'Todavía no hay unidades orgánicas para mostrar.' }}
        </div>
    @else
        <div class="org-tree overflow-x-auto pb-4">
            <ul>
                @foreach ($raices as $nodo)
                    @include('livewire.organigrama._nodo', ['nodo' => $nodo, 'etiquetasTurno' => $etiquetasTurno, 'forzarAbierto' => $buscar !== ''])
                @endforeach
            </ul>
        </div>
    @endif
</div>
