<div>
    @if ($abierto && $t)
        <x-organigrama.drawer :titulo="$t->nombre_completo"
                              :subtitulo="'DNI '.($t->dni ?? '—').' · Régimen '.($t->regimen ?? '—')"
                              icono="user-circle">
            @if ($error)
                <div class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200" role="alert">
                    <span class="mt-1 inline-block size-2 shrink-0 rounded-full bg-red-500"></span>
                    <span>{{ $error }}</span>
                </div>
            @endif

            @if (! $t->activo || $t->sede_id === null)
                <div class="flex flex-wrap gap-2">
                    @unless ($t->activo)<span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-[11px] font-medium text-gray-700">Desactivado</span>@endunless
                    @if ($t->sede_id === null)<span class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-medium text-red-700">Sin sede</span>@endif
                </div>
            @endif

            {{-- Ubicación --}}
            <section class="space-y-4">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Ubicación</h3>

                <x-admin.campo label="Unidad orgánica" for="org-trab-unidad">
                    <x-organigrama.combobox id="org-trab-unidad" model="unidadId" :options="$unidades" placeholder="Buscar unidad…" vacio="— Sin unidad —" />
                </x-admin.campo>

                @if ($destino)
                    <div class="rounded-xl bg-amber-50 px-3 py-2.5 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200" role="status">
                        Al guardar pasará a depender de
                        <strong>{{ $destino->jefe?->nombre_completo ?? 'nadie (la unidad no tiene jefe)' }}</strong>.
                        @if ($destino->jefe?->sede && $destino->jefe->sede_id !== $t->sede_id)
                            El jefe está en <strong>{{ $destino->jefe->sede->nombre }}</strong>; la sede no cambia sola, ajústala abajo si hace falta.
                        @endif
                    </div>
                @endif

                <x-admin.campo label="Sede" for="org-trab-sede">
                    <x-organigrama.combobox id="org-trab-sede" model="sedeId" :options="$sedes" placeholder="Buscar sede…" vacio="— Sin sede —" />
                </x-admin.campo>
            </section>

            {{-- Estado --}}
            <section class="border-t border-gray-100 pt-5 dark:border-white/10">
                <x-organigrama.switch model="activo" label="Trabajador activo" ayuda="Si lo desactivas, deja de aparecer salvo que marques «Mostrar desactivados»." />
            </section>

            {{-- Jefes --}}
            <section class="space-y-3 border-t border-gray-100 pt-5 dark:border-white/10">
                <div>
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Jefes inmediatos</h3>
                    <p class="text-xs text-gray-500 dark:text-tinta-50/60">Los cambios de esta sección se aplican al instante, sin pulsar Guardar.</p>
                </div>

                <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                    <span class="text-tinta-950 dark:text-white">{{ $t->jefeInmediato?->nombre_completo ?? 'Sin jefe inmediato' }}</span>
                    <span class="text-[11px] text-gray-500 dark:text-tinta-50/60">por su unidad</span>
                </div>

                @foreach ($adicionales as $a)
                    <div class="flex items-center justify-between rounded-xl bg-tinta-50/60 px-3 py-2 text-sm dark:bg-white/5" wire:key="org-adic-{{ $a->id }}">
                        <span class="text-tinta-950 dark:text-white">{{ $a->nombre_completo }}
                            <span class="ml-1 rounded-full bg-tinta-50 px-2 py-0.5 text-[11px] text-tinta-700 dark:bg-white/10 dark:text-tinta-100">adicional</span>
                        </span>
                        <button type="button" wire:click="quitarJefe({{ $a->id }})" wire:confirm="¿Quitar a este jefe adicional?" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" aria-label="Quitar jefe adicional">
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                @endforeach

                @if ($ok)
                    <p class="text-xs font-medium text-emerald-700 dark:text-emerald-300" role="status">{{ $ok }}</p>
                @endif

                <div class="flex items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <x-organigrama.combobox model="nuevoJefeId" :options="$jefes" placeholder="Buscar por nombre…" vacio="Agregar jefe adicional…" :limpiable="false" />
                    </div>
                    <button type="button" wire:click="agregarJefe" wire:loading.attr="disabled" wire:target="agregarJefe" class="btn-secondary text-xs">Asignar</button>
                </div>
            </section>

            <a href="{{ route('usuarios-admin.editar', $t) }}" class="inline-flex items-center gap-1 text-xs text-tinta-700 hover:underline dark:text-tinta-200">
                <x-icon name="cog" class="size-4" /> Edición completa (datos, roles, turno)
            </a>

            <x-slot:pie>
                <button type="button" @click="cerrar()" class="btn-secondary text-sm">Cancelar</button>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar" class="btn-primary inline-flex items-center gap-2 text-sm">
                    <svg wire:loading wire:target="guardar" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                    Guardar cambios
                </button>
            </x-slot:pie>
        </x-organigrama.drawer>
    @endif
</div>
