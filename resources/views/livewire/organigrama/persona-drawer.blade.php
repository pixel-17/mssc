<div>
    @if ($abierto && ($creando || $persona))
        <x-organigrama.drawer :titulo="$creando ? ($tipo === 'jefe_inmediato' ? 'Nuevo jefe inmediato' : 'Nueva persona') : $persona->nombre_completo"
                              :subtitulo="$creando ? 'Su contraseña inicial será su DNI' : 'DNI '.($persona->dni ?? '—').' · Régimen '.($persona->regimen ?? '—')"
                              :icono="$creando ? 'plus-circle' : 'user-circle'">
            @if ($error)
                <div class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200" role="alert">
                    <span class="mt-1 inline-block size-2 shrink-0 rounded-full bg-red-500"></span>
                    <span>{{ $error }}</span>
                </div>
            @endif

            @unless ($creando)
                @if (! $persona->activo || $persona->sede_id === null)
                    <div class="flex flex-wrap gap-2">
                        @unless ($persona->activo)<span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-[11px] font-medium text-gray-700">Desactivado</span>@endunless
                        @if ($persona->sede_id === null)<span class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-medium text-red-700">Sin sede</span>@endif
                    </div>
                @endif
            @endunless

            {{-- Tipo (solo alta) --}}
            @if ($creando)
                <section class="space-y-2">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">¿Qué vas a crear?</h3>
                    <div class="grid grid-cols-2 gap-2" role="group" aria-label="Tipo de persona">
                        @foreach (['trabajador' => 'Trabajador', 'jefe_inmediato' => 'Jefe inmediato'] as $valor => $etiqueta)
                            <button type="button" wire:click="elegirTipo('{{ $valor }}')" aria-pressed="{{ $tipo === $valor ? 'true' : 'false' }}"
                                    @class([
                                        'rounded-xl border px-3 py-2 text-sm font-medium transition',
                                        'border-tinta-600 bg-tinta-600 text-white' => $tipo === $valor,
                                        'border-gray-200 text-tinta-950 hover:bg-gray-50 dark:border-white/15 dark:text-white dark:hover:bg-white/5' => $tipo !== $valor,
                                    ])>{{ $etiqueta }}</button>
                        @endforeach
                    </div>
                    @if ($tipo === 'jefe_inmediato')
                        <p class="text-xs text-gray-500 dark:text-tinta-50/60">Quedará como jefe de la unidad que elijas abajo: eso lo convierte en jefe inmediato de todos los que pertenezcan a ella. No lleva turno: él programa el suyo.</p>
                    @endif
                </section>
            @endif

            {{-- Datos personales --}}
            <section class="space-y-4">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Datos personales</h3>

                <div class="grid grid-cols-2 gap-3">
                    <x-admin.campo label="Nombres" for="persona-name" campo="name">
                        <x-input id="persona-name" type="text" wire:model="name" autocomplete="off" />
                    </x-admin.campo>
                    <x-admin.campo label="Apellidos" for="persona-apellido" campo="apellido">
                        <x-input id="persona-apellido" type="text" wire:model="apellido" autocomplete="off" />
                    </x-admin.campo>
                </div>

                @if ($creando)
                    <div class="grid grid-cols-2 gap-3">
                        <x-admin.campo label="DNI" for="persona-dni" campo="dni">
                            <x-input id="persona-dni" type="text" wire:model="dni" maxlength="8" inputmode="numeric" autocomplete="off" />
                        </x-admin.campo>
                        <x-admin.campo label="Régimen" for="persona-regimen" campo="regimen"
                                       :ayuda="$tipo === 'trabajador' && $destino?->jefe?->regimen ? 'Debe coincidir con el de su jefe inmediato ('.$destino->jefe->regimen.').' : null">
                            <x-select id="persona-regimen" wire:model="regimen">
                                <option value="">— Selecciona —</option>
                                <option value="276">276 (día)</option>
                                <option value="728">728 (rotativo)</option>
                            </x-select>
                        </x-admin.campo>
                    </div>
                @endif

                <x-admin.campo label="Correo" for="persona-email" campo="email">
                    <x-input id="persona-email" type="email" wire:model="email" autocomplete="off" />
                </x-admin.campo>
            </section>

            {{-- Ubicación --}}
            <section class="space-y-4 border-t border-gray-100 pt-5 dark:border-white/10">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Ubicación</h3>

                @if ($encabeza->isNotEmpty())
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5 text-sm dark:bg-white/5">
                        <p class="text-tinta-950 dark:text-white">{{ $persona->unidadOrganica?->nombre ?? 'Sin unidad' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-tinta-50/60">Encabeza {{ $encabeza->implode(', ') }}: para moverlo usa «Mover a otra unidad» desde su menú en el organigrama.</p>
                    </div>
                @else
                    <x-admin.campo label="Unidad orgánica" for="persona-unidad" campo="unidadId">
                        <x-organigrama.combobox id="persona-unidad" model="unidadId" :options="$unidades" :live="true" placeholder="Buscar unidad…"
                                                :vacio="$creando ? 'Elige la unidad' : '— Sin unidad —'" :limpiable="! $creando" />
                    </x-admin.campo>
                @endif

                {{-- Qué pasa con la unidad elegida --}}
                @if ($destino && $encabeza->isEmpty())
                    @if ($creando && $tipo === 'jefe_inmediato')
                        <div @class([
                            'rounded-xl px-3 py-2.5 text-xs',
                            'bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200' => $destino->jefe,
                            'bg-tinta-50 text-tinta-800 dark:bg-white/5 dark:text-tinta-100' => ! $destino->jefe,
                        ]) role="status">
                            @if ($destino->jefe)
                                {{ $destino->nombre }} ya tiene a <strong>{{ $destino->jefe->nombre_completo }}</strong> como jefe: cámbialo primero desde la unidad antes de crear otro.
                            @else
                                Quedará como jefe inmediato de <strong>{{ $destino->nombre }}</strong>.
                            @endif
                        </div>
                    @elseif ($creando || (int) $destino->id !== (int) $persona->unidad_organica_id)
                        <div @class([
                            'rounded-xl px-3 py-2.5 text-xs',
                            'bg-tinta-50 text-tinta-800 dark:bg-white/5 dark:text-tinta-100' => $destino->jefe,
                            'bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200' => ! $destino->jefe,
                        ]) role="status">
                            {{ $creando ? 'Pasará a depender de' : 'Al guardar pasará a depender de' }}
                            <strong>{{ $destino->jefe?->nombre_completo ?? 'nadie (la unidad no tiene jefe)' }}</strong>.
                            @if (! $creando && $destino->jefe?->sede && (int) $destino->jefe->sede_id !== (int) $persona->sede_id)
                                El jefe está en <strong>{{ $destino->jefe->sede->nombre }}</strong>; la sede no cambia sola, ajústala abajo si hace falta.
                            @endif
                        </div>
                    @endif
                @endif

                {{-- Sede: del trabajador nuevo se hereda; del jefe inmediato nuevo y en la edición se elige. --}}
                @if ($creando && $tipo === 'trabajador')
                    <div class="rounded-xl border border-tinta-100 bg-tinta-50/60 px-3 py-2.5 dark:border-white/10 dark:bg-white/5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Sede del trabajador</p>
                        <p class="mt-0.5 text-sm font-medium text-tinta-950 dark:text-white">{{ $sedeHeredada ?? 'Elige primero la unidad' }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-tinta-50/60">Se asigna sola: es la sede del jefe inmediato de la unidad.</p>
                    </div>
                @else
                    <x-admin.campo :label="$creando || ! $esAdmin ? 'Sede (obligatoria)' : 'Sede'" for="persona-sede" campo="sedeId">
                        {{-- Solo el admin editando puede dejarla sin sede; al crear y para el jefe de área es obligatoria. --}}
                        <x-organigrama.combobox id="persona-sede" model="sedeId" :options="$sedes" :limpiable="! $creando && $esAdmin" placeholder="Buscar sede…" vacio="— Sin sede —" />
                    </x-admin.campo>
                @endif
            </section>

            {{-- Estado (solo admin, solo edición) --}}
            @if (! $creando && $esAdmin)
                <section class="border-t border-gray-100 pt-5 dark:border-white/10">
                    <x-organigrama.switch model="activo" label="Persona activa" ayuda="Si la desactivas, deja de aparecer salvo que marques «Mostrar desactivados»." />
                </section>
            @endif

            @unless ($creando)
                <section class="space-y-3 border-t border-gray-100 pt-5 dark:border-white/10">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Jefe inmediato</h3>
                    <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                        <span class="text-tinta-950 dark:text-white">{{ $persona->jefeInmediato?->nombre_completo ?? 'Sin jefe inmediato' }}</span>
                        <span class="text-[11px] text-gray-500 dark:text-tinta-50/60">por su unidad</span>
                    </div>
                </section>

                @if ($esAdmin)
                    <a href="{{ route('usuarios.editar', $persona) }}?volver=organigrama" class="inline-flex items-center gap-1 text-xs text-tinta-700 hover:underline dark:text-tinta-200">
                        <x-icon name="cog" class="size-4" /> Edición completa (DNI, régimen, roles, contraseña, turno)
                    </a>
                @elseif ($persona->regimen === '728')
                    <a href="{{ route('turnos.configuracion', $persona) }}" class="inline-flex items-center gap-1 text-xs text-tinta-700 hover:underline dark:text-tinta-200">
                        <x-icon name="clock" class="size-4" /> Configurar turno
                    </a>
                @endif
            @endunless

            <x-slot:pie>
                <button type="button" @click="cerrar()" class="btn-secondary text-sm">Cancelar</button>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar" class="btn-primary inline-flex items-center gap-2 text-sm">
                    <svg wire:loading wire:target="guardar" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                    {{ $creando ? 'Crear' : 'Guardar cambios' }}
                </button>
            </x-slot:pie>
        </x-organigrama.drawer>
    @endif
</div>
