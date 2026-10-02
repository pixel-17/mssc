@php
    /** @var \App\Models\UnidadOrganica $unidad */
    $unidad = $nodo['unidad'];
    $jefePrincipal = $unidad->jefe;
    $tieneHijos = $nodo['hijos']->isNotEmpty();
    $tieneMiembros = $nodo['miembros']->isNotEmpty();
    $sedeJefe = $jefePrincipal?->sede_id;

    // Nivel en el árbol (0 = raíz): decide el tono de la tarjeta (degradé de una sola gama).
    $nivel = $nivel ?? 0;

    // Vista inicial "solo hasta las áreas": la raíz abre para mostrar sus sub-unidades; las demás
    // abren solo si tienen sub-áreas debajo. Las oficinas (sin sub-unidades) y su gente quedan cerradas.
    $tieneSubareas = $nodo['hijos']->contains(fn ($h) => $h['hijos']->isNotEmpty());
    $abiertoInicial = $nivel === 0 ? $tieneHijos : $tieneSubareas;
@endphp

<li wire:key="org-{{ $unidad->id }}"
    x-data="{ open: {{ $abiertoInicial ? 'true' : 'false' }} }"
    @if ($forzarAbierto) x-init="open = true" @endif
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
            :class="sobre === {{ $unidad->id }} && 'ring-2 ring-amber-400'"
            @dragover.prevent="if (arrastrando) { $event.dataTransfer.dropEffect = 'move'; sobre = {{ $unidad->id }} }"
            @dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) sobre = null"
            @drop.prevent="if (arrastrando) { $wire.proponerMovimiento(arrastrando, {{ $unidad->id }}) } arrastrando = null; sobre = null"
        @endif
    >
        {{-- Cabecera de la unidad --}}
        <div class="flex items-start gap-2">
            @if ($tieneHijos || $tieneMiembros)
                <button type="button" @click="open = ! open" class="mt-0.5 rounded p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10" :aria-expanded="open" aria-label="Mostrar u ocultar">
                    <svg class="size-4 transition-transform" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </button>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <x-icon name="building" class="size-4 text-tinta-600 dark:text-tinta-200" />
                    <h3 class="font-semibold text-tinta-950 dark:text-white">{{ $unidad->nombre }}</h3>
                    @if ($unidad->tipo)
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600 dark:bg-white/10 dark:text-tinta-50/70">{{ $unidad->tipo }}</span>
                    @endif
                    @unless ($unidad->activo)
                        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] text-gray-700">Unidad desactivada</span>
                    @endunless
                    <span class="ms-auto text-xs text-gray-500 dark:text-tinta-50/60">{{ $nodo['total'] }} {{ $nodo['total'] === 1 ? 'persona' : 'personas' }}</span>
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
                            <a href="{{ route('usuarios-admin.crear', ['unidad' => $unidad->id]) }}" title="Agregar trabajador a esta unidad" class="rounded-lg p-1.5 text-gray-500 hover:bg-tinta-50 hover:text-tinta-700 dark:text-tinta-100 dark:hover:bg-white/10">
                                <x-icon name="users" class="size-4" /><span class="sr-only">Agregar trabajador</span>
                            </a>
                        </div>
                    @endif
                </div>

                @if ($nodo['turnos_sin_jefe'] !== [])
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        Turnos sin jefe: {{ collect($nodo['turnos_sin_jefe'])->map(fn ($t) => $etiquetasTurno[$t] ?? $t)->implode(', ') }}
                    </p>
                @endif

                {{-- Jefes de la unidad --}}
                <div class="mt-3 space-y-1.5">
                    @forelse ($nodo['jefes'] as $jefe)
                        {{-- Se arrastra el jefe titular de la unidad, inmediato o de área (solo el admin, en modo edición). --}}
                        @php($puedeArrastrarJefe = $modoEdicion && $esAdmin && $jefe->id === $jefePrincipal?->id)
                        <div @class([
                            'org-persona flex flex-wrap items-center gap-2 text-sm',
                            'opacity-50' => ! $jefe->activo,
                            'opacity-40' => $nodo['filtro_sede'] && ! in_array($jefe->id, $nodo['jefes_en_filtro'], true),
                            'cursor-grab active:cursor-grabbing' => $puedeArrastrarJefe,
                        ])
                             @if ($puedeArrastrarJefe)
                                 draggable="true"
                                 title="Arrástralo sobre otra unidad: puedes mover solo a la persona (pasa a trabajador) o su unidad entera"
                                 @dragstart="arrastrando = {{ $jefe->id }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $jefe->id }}')"
                                 @dragend="arrastrando = null; sobre = null"
                             @endif
                        >
                            <x-avatar :user="$jefe" />
                            <button type="button" wire:click="verPersona({{ $jefe->id }})" class="font-medium text-tinta-950 hover:underline dark:text-white">{{ $jefe->nombre_completo }}</button>
                            <span class="text-xs text-gray-500 dark:text-tinta-50/60">{{ $jefe->id === $jefePrincipal?->id ? 'Jefe' : 'Jefe de turno' }}</span>
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
                        <div wire:key="org-m-{{ $m->id }}" class="org-persona flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5 text-sm dark:bg-white/5 {{ $m->activo ? '' : 'opacity-50' }} {{ $modoEdicion ? 'cursor-grab active:cursor-grabbing' : '' }}"
                             @if ($modoEdicion)
                                 draggable="true"
                                 @dragstart="arrastrando = {{ $m->id }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $m->id }}')"
                                 @dragend="arrastrando = null; sobre = null"
                             @endif
                        >
                            <x-avatar :user="$m" class="ring-2 ring-emerald-500" />
                            <button type="button" wire:click="verPersona({{ $m->id }})" class="min-w-0 truncate text-left text-tinta-950 hover:underline dark:text-white">{{ $m->nombre_completo }}</button>
                            @include('livewire.organigrama._chips', ['persona' => $m, 'sedeReferencia' => $sedeJefe])
                            @if ($m->jefesInmediatosAdicionales->isNotEmpty())
                                <span class="rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100"
                                      title="Jefes adicionales: {{ $m->jefesInmediatosAdicionales->map->nombre_completo->implode(', ') }}">
                                    +{{ $m->jefesInmediatosAdicionales->count() }} {{ $m->jefesInmediatosAdicionales->count() === 1 ? 'jefe adicional' : 'jefes adicionales' }}
                                </span>
                            @endif
                            @unless ($m->activo)<span class="text-[11px] text-red-600">desactivado</span>@endunless
                            @if ($modoEdicion && $esAdmin)
                                <button type="button" title="Editar trabajador" class="ms-auto rounded-lg p-1 text-gray-500 opacity-70 hover:bg-white hover:text-tinta-700 hover:opacity-100 dark:hover:bg-white/10"
                                        @click="$dispatch('org-trabajador-abrir', { id: {{ $m->id }} })">
                                    <x-icon name="pencil" class="size-3.5" /><span class="sr-only">Editar trabajador</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Sub-unidades --}}
    @if ($tieneHijos)
        <ul x-show="open" x-collapse>
            @foreach ($nodo['hijos'] as $hijo)
                @include('livewire.organigrama._nodo', ['nodo' => $hijo, 'etiquetasTurno' => $etiquetasTurno, 'forzarAbierto' => $forzarAbierto, 'esAdmin' => $esAdmin, 'modoEdicion' => $modoEdicion, 'nivel' => $nivel + 1])
            @endforeach
        </ul>
    @endif
</li>
