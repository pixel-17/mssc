@php
    /** @var \App\Models\UnidadOrganica $unidad */
    $unidad = $nodo['unidad'];
    $jefePrincipal = $unidad->jefe;
    $tieneHijos = $nodo['hijos']->isNotEmpty();
    $tieneMiembros = $nodo['miembros']->isNotEmpty();
    $sedeJefe = $jefePrincipal?->sede_id;
@endphp

<li wire:key="org-{{ $unidad->id }}"
    x-data="{ open: true }"
    @if ($forzarAbierto) x-init="open = true" @endif
    @org-expandir.window="open = true"
    @org-contraer.window="open = false">

    <div @class([
        'glass-card p-4 min-w-[18rem] max-w-3xl',
        'opacity-60' => ! $unidad->activo,
    ])>
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
                </div>

                @if ($nodo['turnos_sin_jefe'] !== [])
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        Turnos sin jefe: {{ collect($nodo['turnos_sin_jefe'])->map(fn ($t) => $etiquetasTurno[$t] ?? $t)->implode(', ') }}
                    </p>
                @endif

                {{-- Jefes de la unidad --}}
                <div class="mt-3 space-y-1.5">
                    @forelse ($nodo['jefes'] as $jefe)
                        <div class="flex flex-wrap items-center gap-2 text-sm {{ $jefe->activo ? '' : 'opacity-50' }}">
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-tinta-600 text-[10px] font-semibold text-white">{{ mb_strtoupper(mb_substr($jefe->name, 0, 1).mb_substr((string) $jefe->apellido, 0, 1)) }}</span>
                            <span class="font-medium text-tinta-950 dark:text-white">{{ $jefe->nombre_completo }}</span>
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
                        <div wire:key="org-m-{{ $m->id }}" class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5 text-sm dark:bg-white/5 {{ $m->activo ? '' : 'opacity-50' }}">
                            <span class="inline-block size-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                            <span class="min-w-0 truncate text-tinta-950 dark:text-white">{{ $m->nombre_completo }}</span>
                            @include('livewire.organigrama._chips', ['persona' => $m, 'sedeReferencia' => $sedeJefe])
                            @if ($m->jefesInmediatosAdicionales->isNotEmpty())
                                <span class="rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100"
                                      title="Jefes adicionales: {{ $m->jefesInmediatosAdicionales->map->nombre_completo->implode(', ') }}">
                                    +{{ $m->jefesInmediatosAdicionales->count() }} {{ $m->jefesInmediatosAdicionales->count() === 1 ? 'jefe adicional' : 'jefes adicionales' }}
                                </span>
                            @endif
                            @unless ($m->activo)<span class="text-[11px] text-red-600">desactivado</span>@endunless
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
                @include('livewire.organigrama._nodo', ['nodo' => $hijo, 'etiquetasTurno' => $etiquetasTurno, 'forzarAbierto' => $forzarAbierto])
            @endforeach
        </ul>
    @endif
</li>
