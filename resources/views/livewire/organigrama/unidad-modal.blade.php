<div>
    @if ($abierto)
        <x-organigrama.drawer :titulo="$unidadId ? 'Editar unidad' : ($parentId ? 'Nueva sub-unidad' : 'Nueva unidad')"
                              :subtitulo="$contexto"
                              icono="building">
            @if ($error)
                <div class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200" role="alert">
                    <span class="mt-1 inline-block size-2 shrink-0 rounded-full bg-red-500"></span>
                    <span>{{ $error }}</span>
                </div>
            @endif

            {{-- Datos --}}
            <section class="space-y-4">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Datos</h3>

                <x-admin.campo label="Nombre" for="org-unidad-nombre">
                    <x-input id="org-unidad-nombre" type="text" wire:model="nombre" wire:keydown.enter="guardar" placeholder="Ej. Oficina de Logística" class="w-full" autofocus />
                    @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </x-admin.campo>

                <x-admin.campo label="Tipo" for="org-unidad-tipo" ayuda="Solo se muestra en el organigrama; no afecta el escalamiento de papeletas.">
                    <x-select id="org-unidad-tipo" wire:model="tipo">
                        <option value="">— Sin tipo —</option>
                        @foreach (\App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::TIPOS as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-select>
                </x-admin.campo>

                <x-admin.campo label="Depende de" for="org-unidad-padre">
                    <x-organigrama.combobox id="org-unidad-padre" model="parentId" :options="$padres" placeholder="Buscar unidad…" vacio="— Unidad raíz —" />
                </x-admin.campo>
            </section>

            {{-- Jefatura --}}
            <section class="space-y-4 border-t border-gray-100 pt-5 dark:border-white/10">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Jefatura</h3>

                <x-admin.campo label="Jefe inmediato de la unidad" for="org-unidad-jefe" ayuda="Es el jefe inmediato de todos sus trabajadores.">
                    <x-organigrama.combobox id="org-unidad-jefe" model="jefeId" :options="$jefes" placeholder="Buscar por nombre…" vacio="— Sin jefe —" />
                </x-admin.campo>

                @if ($unidadId)
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-tinta-950 dark:text-white">Jefes de turno adicionales <span class="font-normal text-gray-500 dark:text-tinta-50/60">(728)</span></p>
                            <button type="button" wire:click="agregarJefeAdicional" class="inline-flex items-center gap-1 text-xs font-medium text-tinta-700 hover:underline dark:text-tinta-200">
                                <x-icon name="plus-circle" class="size-4" /> Agregar
                            </button>
                        </div>

                        @forelse ($jefesAdicionales as $indice => $jefeAdicional)
                            <div class="flex items-center gap-2" wire:key="org-ja-{{ $indice }}">
                                <div class="min-w-0 flex-1">
                                    <x-organigrama.combobox :model="'jefesAdicionales.'.$indice" :options="$jefes" placeholder="Buscar por nombre…" vacio="Elegir jefe…" :limpiable="false" />
                                </div>
                                <button type="button" wire:click="quitarJefeAdicional({{ $indice }})" class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" aria-label="Quitar jefe">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        @empty
                            <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-white/5 dark:text-tinta-50/60">Ninguno. El turno que cubre cada uno sale de su propio calendario.</p>
                        @endforelse
                    </div>
                @else
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-white/5 dark:text-tinta-50/60">Los jefes de turno adicionales se agregan después de crear la unidad.</p>
                @endif
            </section>

            {{-- Estado --}}
            <section class="border-t border-gray-100 pt-5 dark:border-white/10">
                <x-organigrama.switch model="activo" label="Unidad activa" ayuda="Una unidad desactivada sigue visible, atenuada, en el organigrama." />
            </section>

            {{-- Zona de riesgo --}}
            @if ($unidadId)
                <section class="space-y-2 rounded-xl border border-red-200/70 p-4 dark:border-red-500/20" x-data="{ confirmar: false }">
                    <h3 class="text-sm font-semibold text-red-700 dark:text-red-300">Eliminar unidad</h3>

                    @if (($resumen['personas'] ?? 0) > 0 || ($resumen['hijos'] ?? 0) > 0)
                        <p class="text-xs text-gray-600 dark:text-tinta-50/70">
                            No se puede eliminar mientras tenga
                            {{ collect([
                                ($resumen['personas'] ?? 0) > 0 ? $resumen['personas'].' '.($resumen['personas'] === 1 ? 'persona' : 'personas') : null,
                                ($resumen['hijos'] ?? 0) > 0 ? $resumen['hijos'].' '.($resumen['hijos'] === 1 ? 'sub-unidad' : 'sub-unidades') : null,
                            ])->filter()->implode(' y ') }}.
                            Muévelas primero o desactiva la unidad.
                        </p>
                    @else
                        <p class="text-xs text-gray-600 dark:text-tinta-50/70">La unidad está vacía. Esta acción no se puede deshacer.</p>
                        <button type="button" x-show="! confirmar" @click="confirmar = true" class="text-sm font-medium text-red-600 hover:underline">Eliminar unidad…</button>
                        <div x-show="confirmar" x-cloak class="flex items-center gap-3 text-sm">
                            <span class="text-gray-700 dark:text-tinta-50/80">¿Seguro?</span>
                            <button type="button" wire:click="eliminar" class="rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white hover:bg-red-700">Sí, eliminar</button>
                            <button type="button" @click="confirmar = false" class="text-xs text-gray-600 hover:underline dark:text-tinta-50/70">Cancelar</button>
                        </div>
                    @endif
                </section>
            @endif

            <x-slot:pie>
                <button type="button" @click="cerrar()" class="btn-secondary text-sm">Cancelar</button>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar" class="btn-primary inline-flex items-center gap-2 text-sm">
                    <svg wire:loading wire:target="guardar" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                    {{ $unidadId ? 'Guardar cambios' : 'Crear unidad' }}
                </button>
            </x-slot:pie>
        </x-organigrama.drawer>
    @endif
</div>
