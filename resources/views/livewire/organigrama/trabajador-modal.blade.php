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

            @if ($ok)
                <div class="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200" role="status">
                    <span class="mt-1 inline-block size-2 shrink-0 rounded-full bg-emerald-500"></span>
                    <span>{{ $ok }}</span>
                </div>
            @endif

            {{-- Ubicación --}}
            <section class="space-y-4">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Ubicación</h3>

                <x-admin.campo label="Unidad orgánica" for="trabajador-unidad" campo="unidadId">
                    <x-organigrama.combobox id="trabajador-unidad" model="unidadId" :options="$unidades" :live="true" placeholder="Buscar unidad…" vacio="— Sin unidad —" />
                </x-admin.campo>

                @if ($destino)
                    <div @class([
                        'rounded-xl px-3 py-2.5 text-xs',
                        'bg-tinta-50 text-tinta-800 dark:bg-white/5 dark:text-tinta-100' => $destino->jefe,
                        'bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200' => ! $destino->jefe,
                    ]) role="status">
                        Al guardar pasará a depender de
                        <strong>{{ $destino->jefe?->nombre_completo ?? 'nadie (la unidad no tiene jefe)' }}</strong>.
                        @if ($destino->jefe?->sede && (int) $destino->jefe->sede_id !== (int) $t->sede_id)
                            El jefe está en <strong>{{ $destino->jefe->sede->nombre }}</strong>; la sede no cambia sola, ajústala abajo si hace falta.
                        @endif
                    </div>
                @endif

                <x-admin.campo label="Sede" for="trabajador-sede" campo="sedeId">
                    <x-organigrama.combobox id="trabajador-sede" model="sedeId" :options="$sedes" :limpiable="true" placeholder="Buscar sede…" vacio="— Sin sede —" />
                </x-admin.campo>
            </section>

            {{-- Estado --}}
            <section class="border-t border-gray-100 pt-5 dark:border-white/10">
                <x-organigrama.switch model="activo" label="Trabajador activo" ayuda="Si lo desactivas, deja de aparecer salvo que marques «Mostrar desactivados»." />
            </section>

            <x-slot:pie>
                <button type="button" wire:click="cerrar" class="btn-secondary text-sm">Cancelar</button>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar" class="btn-primary inline-flex items-center gap-2 text-sm">
                    <svg wire:loading wire:target="guardar" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                    Guardar cambios
                </button>
            </x-slot:pie>
        </x-organigrama.drawer>
    @endif
</div>
