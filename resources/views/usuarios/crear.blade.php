@php
    // Sede que heredará un trabajador nuevo (ver CrearUsuarioAction::resolverSede):
    // la del jefe de la unidad elegida y, si ese jefe no tiene, la del creador.
    // Se arma aquí para mostrarla en vivo en el formulario, solo como dato de lectura.
    $sedeCreador = auth()->user()->sede?->nombre;
    $sedePorUnidad = $unidades->mapWithKeys(fn ($u) => [$u->id => $u->jefe?->sede?->nombre])->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-admin.encabezado titulo="Crear usuario" />
    </x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        <x-validation-errors />

        <div class="glass-card p-4 sm:p-6">
            <form method="POST" action="{{ route('usuarios.store') }}" class="space-y-6"
                  x-data="{
                      tipo: @js(old('tipo', 'trabajador')),
                      regimen: @js(old('regimen', $regimenCreador) ?? ''),
                      unidad: @js((string) old('unidad_organica_id', '')),
                      sedePorUnidad: @js($sedePorUnidad),
                      sedeCreador: @js($sedeCreador),
                      enviando: false,
                      get sedeHeredada() {
                          if (! this.unidad) return null;
                          return this.sedePorUnidad[this.unidad] ?? this.sedeCreador ?? 'Sin sede';
                      },
                  }"
                  @submit="enviando = true"
                  @pageshow.window="enviando = false">
                @csrf

                @if ($esJefeDeArea)
                    <div>
                        <x-label for="tipo" value="¿Qué vas a crear?" />
                        <x-select id="tipo" name="tipo" x-model="tipo" required class="mt-1 block w-full">
                            <option value="trabajador">Trabajador</option>
                            <option value="jefe_inmediato">Jefe Inmediato</option>
                        </x-select>
                        <x-input-error for="tipo" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-tinta-100/60" x-show="tipo === 'jefe_inmediato'" x-cloak>
                            Quedará como jefe de la unidad que elijas abajo — eso es lo que lo convierte en Jefe Inmediato de todos los que pertenezcan a esa unidad.
                        </p>
                    </div>

                    <div>
                        <x-label for="unidad_organica_id" value="Unidad orgánica" />
                        <x-select id="unidad_organica_id" name="unidad_organica_id" x-model="unidad" required class="mt-1 block w-full">
                            <option value="">Selecciona una unidad de tu área</option>
                            @foreach ($unidades as $unidad)
                                <option value="{{ $unidad->id }}" @selected(old('unidad_organica_id') == $unidad->id)>{{ $unidad->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="unidad_organica_id" class="mt-2" />
                    </div>

                    {{-- Sede: del jefe inmediato se elige (obligatoria); del trabajador se hereda y solo se muestra. --}}
                    <div x-show="tipo === 'jefe_inmediato'" x-cloak>
                        <x-label for="sede_id" value="Sede (obligatoria)" />
                        <x-select id="sede_id" name="sede_id" x-bind:disabled="tipo !== 'jefe_inmediato'" x-bind:required="tipo === 'jefe_inmediato'" class="mt-1 block w-full">
                            <option value="">Selecciona una sede</option>
                            @foreach ($sedes as $sede)
                                <option value="{{ $sede->id }}" @selected(old('sede_id', auth()->user()->sede_id) == $sede->id)>{{ $sede->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="sede_id" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-tinta-100/60">Puede ser de otra sede, pero no puede quedar sin sede. No lleva turno: él programa el suyo.</p>
                    </div>

                    <div x-show="tipo !== 'jefe_inmediato'"
                         class="rounded-xl border border-tinta-100 bg-tinta-50/60 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Sede del trabajador</p>
                        <p class="mt-0.5 text-sm font-medium text-tinta-950 dark:text-white"
                           x-text="sedeHeredada ?? 'Elige primero la unidad'"></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-tinta-100/60">Se asigna sola: es la sede del jefe inmediato de la unidad que elijas.</p>
                    </div>
                @else
                    <div class="rounded-xl border border-tinta-200 bg-tinta-50 p-4 text-sm text-tinta-800 dark:border-white/10 dark:bg-white/5 dark:text-tinta-100">
                        Este trabajador quedará bajo tu supervisión: te asignaremos automáticamente como su jefe inmediato,
                        y heredará tu misma sede{{ $sedeCreador ? ' ('.$sedeCreador.')' : '' }} y unidad orgánica (no se pueden editar aquí).
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-label for="name" value="Nombres" />
                        <x-input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="off" class="mt-1 block w-full" />
                        <x-input-error for="name" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="apellido" value="Apellidos" />
                        <x-input id="apellido" name="apellido" type="text" value="{{ old('apellido') }}" required autocomplete="off" class="mt-1 block w-full" />
                        <x-input-error for="apellido" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-label for="dni" value="DNI" />
                        <x-input id="dni" name="dni" type="text" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" autocomplete="off"
                                 title="8 dígitos" value="{{ old('dni') }}" required class="mt-1 block w-full" />
                        <x-input-error for="dni" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="regimen" value="Régimen" />
                        <x-select id="regimen" name="regimen" x-model="regimen" required class="mt-1 block w-full">
                            <option value="">Selecciona</option>
                            <option value="276">276 (día)</option>
                            <option value="728">728 (rotativo)</option>
                        </x-select>
                        <x-input-error for="regimen" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-label for="email" value="Correo" />
                    <x-input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off" class="mt-1 block w-full" />
                    <x-input-error for="email" class="mt-2" />
                </div>

                <div x-show="regimen === '728' && tipo !== 'jefe_inmediato'" x-cloak
                     class="space-y-4 rounded-xl border border-dashed border-tinta-200 p-4 dark:border-white/15">
                    <p class="text-sm font-medium text-tinta-950 dark:text-white">Turno inicial (trabajador 728)</p>
                    <p class="text-xs text-gray-500 dark:text-tinta-100/60">
                        Un trabajador 728 necesita su horario cargado desde el primer día: sin esto no podrá
                        crear ninguna papeleta.
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-label for="turno" value="Turno" />
                            <x-select id="turno" name="turno" class="mt-1 block w-full">
                                <option value="">Selecciona</option>
                                <option value="MANANA" @selected(old('turno') === 'MANANA')>Mañana</option>
                                <option value="TARDE" @selected(old('turno') === 'TARDE')>Tarde</option>
                                <option value="NOCHE" @selected(old('turno') === 'NOCHE')>Noche</option>
                            </x-select>
                            <x-input-error for="turno" class="mt-2" />
                        </div>
                        <div>
                            <x-label for="fecha_ancla" value="Empieza su próximo bloque de trabajo" />
                            <x-input id="fecha_ancla" name="fecha_ancla" type="date" value="{{ old('fecha_ancla', now()->toDateString()) }}" class="mt-1 block w-full" />
                            <x-input-error for="fecha_ancla" class="mt-2" />
                        </div>
                        <div>
                            <x-label for="dias_trabajo" value="Días de trabajo seguidos" />
                            <x-input id="dias_trabajo" name="dias_trabajo" type="number" inputmode="numeric" min="1" max="30" value="{{ old('dias_trabajo', 6) }}" class="mt-1 block w-full" />
                            <x-input-error for="dias_trabajo" class="mt-2" />
                        </div>
                        <div>
                            <x-label for="dias_descanso" value="Días de descanso" />
                            <x-input id="dias_descanso" name="dias_descanso" type="number" inputmode="numeric" min="1" max="30" value="{{ old('dias_descanso', 1) }}" class="mt-1 block w-full" />
                            <x-input-error for="dias_descanso" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-tinta-50/60 px-3 py-2 dark:bg-white/5">
                    <p class="text-xs text-gray-600 dark:text-tinta-100/70">
                        Su contraseña inicial será su DNI. El sistema le pedirá actualizarla
                        (de forma opcional) la primera vez que ingrese.
                    </p>
                </div>

                <x-admin.barra-flotante>
                    <a href="{{ route('usuarios.index') }}" class="btn-secondary text-sm">Cancelar</a>
                    <x-button x-bind:disabled="enviando" class="disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="! enviando">Crear</span>
                        <span x-show="enviando" x-cloak>Creando…</span>
                    </x-button>
                </x-admin.barra-flotante>
            </form>
        </div>
    </div>
</x-app-layout>
