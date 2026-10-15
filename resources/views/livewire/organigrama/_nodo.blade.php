@php
    /** @var \App\Models\UnidadOrganica $unidad */
    $unidad = $nodo['unidad'];
    $jefePrincipal = $unidad->jefe;
    $tieneHijos = $nodo['hijos']->isNotEmpty();
    $tieneMiembros = $nodo['miembros']->isNotEmpty();
    $sedeJefe = $jefePrincipal?->sede_id;

    // Nivel en el árbol (0 = raíz): decide el tono de la tarjeta (degradé de una sola gama).
    $nivel = $nivel ?? 0;

    // Texto de la búsqueda: se resalta donde coincide (nombre de unidad, jefes y trabajadores). Siempre devuelve HTML ya escapado.
    $buscar = trim((string) ($buscar ?? ''));
    $resaltar = function (?string $texto) use ($buscar): \Illuminate\Support\HtmlString {
        $texto = (string) $texto;
        if ($buscar === '') {
            return new \Illuminate\Support\HtmlString(e($texto));
        }
        $html = '';
        foreach (preg_split('/('.preg_quote($buscar, '/').')/iu', $texto, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$texto] as $i => $parte) {
            $html .= $i % 2 === 1
                ? '<mark class="rounded bg-amber-200 px-0.5 text-inherit dark:bg-amber-400/40">'.e($parte).'</mark>'
                : e($parte);
        }

        return new \Illuminate\Support\HtmlString($html);
    };

    // Unidades con mucha gente: se muestran los primeros y el resto con un botón (con filtros activos se ve todo).
    $maxVisibles = 8;

    // Vista inicial "solo hasta las áreas": la raíz abre para mostrar sus sub-unidades; las demás
    // abren solo si tienen sub-áreas debajo. Las oficinas (sin sub-unidades) y su gente quedan cerradas.
    $tieneSubareas = $nodo['hijos']->contains(fn ($h) => $h['hijos']->isNotEmpty());
    $abiertoInicial = $nivel === 0 ? $tieneHijos : $tieneSubareas;
@endphp

<li wire:key="org-{{ $unidad->id }}" id="org-unidad-{{ $unidad->id }}"
    x-data="{ open: {{ $abiertoInicial ? 'true' : 'false' }}, foco: false, verTodos: {{ $forzarAbierto ? 'true' : 'false' }} }"
    :class="foco && 'org-foco'"
    @if ($forzarAbierto) x-init="open = true" @endif
    @org-ir.window="
        if ($event.detail.ruta.includes({{ $unidad->id }})) open = true;
        if ($event.detail.id === {{ $unidad->id }}) {
            setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); foco = true; setTimeout(() => foco = false, 2200) }, 380)
        }
    "
    @org-expandir.window="open = true"
    @org-contraer.window="open = false"
    @org-areas.window="open = {{ $abiertoInicial ? 'true' : 'false' }}">

    {{-- En modo edición la tarjeta es zona de soltar: soltar NO mueve, pide confirmación (proponerMovimiento). --}}
    <div @class([
        'group glass-card org-nivel p-4 min-w-[18rem] max-w-3xl',
        'org-n'.min($nivel, 5),
        'opacity-60' => ! $unidad->activo,
    ])
        @if ($modoEdicion)
            :class="sobre === {{ $unidad->id }} && 'ring-2 ring-amber-400 bg-amber-50/60 dark:bg-amber-500/10'"
            @dragover.prevent="if (arrastrando) { $event.dataTransfer.dropEffect = 'move'; sobre = {{ $unidad->id }} }"
            @dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) sobre = null"
            @drop.prevent="if (arrastrando) { $wire.proponerMovimiento(arrastrando, {{ $unidad->id }}) } arrastrando = null; sobre = null"
        @endif
    >
        {{-- Cabecera de la unidad --}}
        <div class="flex items-start gap-2">
            @if ($tieneHijos || $tieneMiembros)
                <button type="button" @click="open = ! open" class="-ms-1 mt-0 grid size-8 shrink-0 place-items-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10" :aria-expanded="open"
                        :aria-label="(open ? 'Contraer ' : 'Expandir ') + @js($unidad->nombre)" :title="open ? 'Contraer' : 'Expandir'">
                    <svg class="size-4 transition-transform" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </button>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <x-icon name="building" class="size-4 text-tinta-600 dark:text-tinta-200" />
                    <h3 class="font-semibold text-tinta-950 dark:text-white">{{ $resaltar($unidad->nombre) }}</h3>
                    @if ($unidad->tipo)
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600 dark:bg-white/10 dark:text-tinta-50/70">{{ $unidad->tipo }}</span>
                    @endif
                    @unless ($unidad->activo)
                        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] text-gray-700">Unidad desactivada</span>
                    @endunless
                    @if ($nodo['sin_jefe'])
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                            <span class="inline-block size-1.5 rounded-full bg-amber-500"></span> Sin jefe activo
                        </span>
                    @endif
                    <span class="ms-auto text-xs text-gray-500 dark:text-tinta-50/60">
                        @if ($tieneHijos){{ $nodo['hijos']->count() }} {{ $nodo['hijos']->count() === 1 ? 'sub-unidad' : 'sub-unidades' }} · @endif{{ $nodo['total'] }} {{ $nodo['total'] === 1 ? 'persona' : 'personas' }}
                    </span>
                    @if ($modoEdicion && $esAdmin)
                        <div class="flex items-center gap-0.5 opacity-70 transition group-hover:opacity-100 group-focus-within:opacity-100">
                            <button type="button" title="Editar unidad" class="rounded-lg p-1.5 text-gray-500 hover:bg-tinta-50 hover:text-tinta-700 dark:text-tinta-100 dark:hover:bg-white/10"
                                    @click="$dispatch('org-unidad-abrir', { id: {{ $unidad->id }}, parentId: null })">
                                <x-icon name="pencil" class="size-4" /><span class="sr-only">Editar unidad</span>
                            </button>
                            <button type="button" title="Agregar sub-unidad" class="rounded-lg p-1.5 text-gray-500 hover:bg-tinta-50 hover:text-tinta-700 dark:text-tinta-100 dark:hover:bg-white/10"
                                    @click="$dispatch('org-unidad-abrir', { id: null, parentId: {{ $unidad->id }} })">
                                <x-icon name="plus-circle" class="size-4" /><span class="sr-only">Agregar sub-unidad</span>
                            </button>
                            <button type="button" title="Agregar trabajador a esta unidad" class="rounded-lg p-1.5 text-gray-500 hover:bg-tinta-50 hover:text-tinta-700 dark:text-tinta-100 dark:hover:bg-white/10"
                                    @click="$dispatch('org-persona-abrir', { unidadId: {{ $unidad->id }} })">
                                <x-icon name="users" class="size-4" /><span class="sr-only">Agregar trabajador</span>
                            </button>
                        </div>
                    @elseif ($modoEdicion)
                        <div class="flex items-center gap-0.5 opacity-70 transition group-hover:opacity-100 group-focus-within:opacity-100">
                            <button type="button" title="Agregar trabajador o jefe inmediato a esta unidad" class="rounded-lg p-1.5 text-gray-500 hover:bg-tinta-50 hover:text-tinta-700 dark:text-tinta-100 dark:hover:bg-white/10"
                                    @click="$dispatch('org-persona-abrir', { unidadId: {{ $unidad->id }} })">
                                <x-icon name="users" class="size-4" /><span class="sr-only">Agregar trabajador o jefe inmediato</span>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Jefes de la unidad --}}
                <div class="mt-3 space-y-1.5">
                    @forelse ($nodo['jefes'] as $jefe)
                        {{-- Se arrastra cualquier jefe de la unidad en modo edición (admin o jefe de área; el servidor valida el alcance). --}}
                        @php($puedeArrastrarJefe = $modoEdicion && ($esAdmin || (int) $jefe->id !== (int) auth()->id()))
                        <div @class([
                            'org-persona flex flex-wrap items-center gap-2 text-sm',
                            'opacity-50' => ! $jefe->activo,
                            'opacity-40' => $nodo['filtro_sede'] && ! in_array($jefe->id, $nodo['jefes_en_filtro'], true),
                            'cursor-grab active:cursor-grabbing' => $puedeArrastrarJefe,
                        ])
                             @if ($puedeArrastrarJefe)
                                 draggable="true"
                                 title="Arrástralo sobre otra unidad para moverlo (se pide confirmación)"
                                 @dragstart="arrastrando = {{ $jefe->id }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $jefe->id }}')"
                                 @dragend="arrastrando = null; sobre = null"
                             @endif
                        >
                            @if ($puedeArrastrarJefe)<svg class="org-asa size-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>@endif
                            <x-avatar :user="$jefe" />
                            <button type="button" wire:click="verPersona({{ $jefe->id }})" class="font-medium text-tinta-950 hover:underline dark:text-white">{{ $resaltar($jefe->nombre_completo) }}</button>
                            <span class="text-xs text-gray-500 dark:text-tinta-50/60">{{ $tieneHijos && $jefe->id === $jefePrincipal?->id ? 'Jefe de área' : 'Jefe inmediato' }}</span>
                            @include('livewire.organigrama._chips', ['persona' => $jefe, 'sedeReferencia' => null])
                            @unless ($jefe->activo)<span class="text-[11px] text-red-600">desactivado</span>@endunless
                        </div>
                    @empty
                        <p class="text-sm text-amber-700 dark:text-amber-400">Esta unidad todavía no tiene jefe.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Trabajadores ligados a esa jefatura --}}
        @if ($tieneMiembros)
            <div x-show="open" x-collapse class="mt-3 border-t border-gray-100 pt-3 dark:border-white/10">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">
                    Trabajadores ({{ $nodo['miembros']->count() }}){{ $jefePrincipal ? ' · a cargo de '.$jefePrincipal->nombre_completo : '' }}
                </p>
                <div class="grid gap-1.5 sm:grid-cols-2">
                    @foreach ($nodo['miembros'] as $m)
                        <div wire:key="org-m-{{ $m->id }}" @if ($loop->index >= $maxVisibles) x-show="verTodos" x-cloak @endif class="org-persona flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5 text-sm dark:bg-white/5 {{ $m->activo ? '' : 'opacity-50' }} {{ $modoEdicion ? 'cursor-grab active:cursor-grabbing' : '' }}"
                             @if ($modoEdicion)
                                 draggable="true"
                                 @dragstart="arrastrando = {{ $m->id }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $m->id }}')"
                                 @dragend="arrastrando = null; sobre = null"
                             @endif
                        >
                            @if ($modoEdicion)<svg class="org-asa size-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>@endif
                            <x-avatar :user="$m" class="ring-2 ring-emerald-500" />
                            <button type="button" wire:click="verPersona({{ $m->id }})" class="min-w-0 truncate text-left text-tinta-950 hover:underline dark:text-white">{{ $resaltar($m->nombre_completo) }}</button>
                            @include('livewire.organigrama._chips', ['persona' => $m, 'sedeReferencia' => $sedeJefe])
                            @unless ($m->activo)<span class="text-[11px] text-red-600">desactivado</span>@endunless
                            @if ($modoEdicion)
                                <button type="button" title="Editar trabajador" class="ms-auto rounded-lg p-1.5 text-gray-500 opacity-70 hover:bg-white hover:text-tinta-700 hover:opacity-100 dark:hover:bg-white/10"
                                        @click="$dispatch('org-persona-abrir', { id: {{ $m->id }} })">
                                    <x-icon name="pencil" class="size-3.5" /><span class="sr-only">Editar trabajador</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($nodo['miembros']->count() > $maxVisibles)
                    <button type="button" class="mt-2 text-xs font-medium text-tinta-700 underline hover:no-underline dark:text-tinta-100" @click="verTodos = ! verTodos" :aria-expanded="verTodos">
                        <span x-show="! verTodos">Mostrar los {{ $nodo['miembros']->count() - $maxVisibles }} restantes</span>
                        <span x-show="verTodos" x-cloak>Mostrar menos</span>
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- Sub-unidades --}}
    @if ($tieneHijos)
        <ul x-show="open" x-collapse>
            @foreach ($nodo['hijos'] as $hijo)
                @include('livewire.organigrama._nodo', ['nodo' => $hijo, 'forzarAbierto' => $forzarAbierto, 'esAdmin' => $esAdmin, 'modoEdicion' => $modoEdicion, 'nivel' => $nivel + 1, 'buscar' => $buscar])
            @endforeach
        </ul>
    @endif
</li>