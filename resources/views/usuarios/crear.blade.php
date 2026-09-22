<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
            Crear usuario
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-validation-errors class="mb-4" />

            <div class="glass-card p-6">
                <form method="POST" action="{{ route('usuarios.store') }}" class="space-y-6"
                      x-data="{ tipo: '{{ old('tipo', 'trabajador') }}', regimen: '{{ old('regimen', $regimenCreador) }}' }">
                    @csrf

                    @if ($esJefeDeArea)
                        <div>
                            <x-label for="tipo" value="¿Qué vas a crear?" />
                            <select id="tipo" name="tipo" x-model="tipo" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                <option value="trabajador">Trabajador</option>
                                <option value="jefe_inmediato">Jefe Inmediato</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500" x-show="tipo === 'jefe_inmediato'">
                                Quedará como jefe de la unidad que elijas abajo — eso es lo que lo convierte en Jefe Inmediato de todos los que pertenezcan a esa unidad.
                            </p>
                        </div>

                        <div>
                            <x-label for="unidad_organica_id" value="Unidad orgánica" />
                            <select id="unidad_organica_id" name="unidad_organica_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                <option value="">Selecciona una unidad de tu área</option>
                                @foreach ($unidades as $unidad)
                                    <option value="{{ $unidad->id }}" @selected(old('unidad_organica_id') == $unidad->id)>{{ $unidad->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="rounded-md bg-tinta-50 border border-tinta-200 p-4 text-sm text-tinta-800">
                            Este trabajador quedará bajo tu supervisión: te asignaremos automáticamente como su jefe inmediato,
                            y heredará tu misma sede y unidad orgánica (no se pueden editar aquí).
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-label for="name" value="Nombres" />
                            <x-input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 block w-full" />
                            <x-input-error for="name" class="mt-2" />
                        </div>
                        <div>
                            <x-label for="apellido" value="Apellidos" />
                            <x-input id="apellido" name="apellido" type="text" value="{{ old('apellido') }}" required class="mt-1 block w-full" />
                            <x-input-error for="apellido" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-label for="dni" value="DNI" />
                            <x-input id="dni" name="dni" type="text" maxlength="8" value="{{ old('dni') }}" required class="mt-1 block w-full" />
                            <x-input-error for="dni" class="mt-2" />
                        </div>
                        <div>
                            <x-label for="regimen" value="Régimen" />
                            <select id="regimen" name="regimen" x-model="regimen" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                <option value="">Selecciona</option>
                                <option value="276">276 (día)</option>
                                <option value="728">728 (rotativo)</option>
                            </select>
                            <x-input-error for="regimen" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-label for="email" value="Correo" />
                        <x-input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full" />
                        <x-input-error for="email" class="mt-2" />
                    </div>

                    <div x-show="regimen === '728'" x-cloak
                         class="rounded-md border border-dashed border-gray-300 dark:border-white/15 p-4 space-y-4">
                        <p class="text-sm font-medium">Turno inicial (régimen 728)</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Un trabajador 728 necesita su horario cargado desde el primer día: sin esto no podrá
                            crear ninguna papeleta.
                        </p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-label for="turno" value="Turno" />
                                <select id="turno" name="turno"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                    <option value="">Selecciona</option>
                                    <option value="MANANA" @selected(old('turno') === 'MANANA')>MANANA</option>
                                    <option value="TARDE" @selected(old('turno') === 'TARDE')>TARDE</option>
                                    <option value="NOCHE" @selected(old('turno') === 'NOCHE')>NOCHE</option>
                                </select>
                                <x-input-error for="turno" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="fecha_ancla" value="Empieza su próximo bloque de trabajo" />
                                <x-input id="fecha_ancla" name="fecha_ancla" type="date" value="{{ old('fecha_ancla', now()->toDateString()) }}" class="mt-1 block w-full" />
                                <x-input-error for="fecha_ancla" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="dias_trabajo" value="Días de trabajo seguidos" />
                                <x-input id="dias_trabajo" name="dias_trabajo" type="number" min="1" max="30" value="{{ old('dias_trabajo', 6) }}" class="mt-1 block w-full" />
                                <x-input-error for="dias_trabajo" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="dias_descanso" value="Días de descanso" />
                                <x-input id="dias_descanso" name="dias_descanso" type="number" min="1" max="30" value="{{ old('dias_descanso', 1) }}" class="mt-1 block w-full" />
                                <x-input-error for="dias_descanso" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Su contraseña inicial será su DNI. El sistema le pedirá actualizarla
                            (de forma opcional) la primera vez que ingrese.
                        </p>
                    </div>

                    @if ($esJefeDeArea)
                        <div>
                            <x-label for="sede_id" value="Sede (opcional)" />
                            <select id="sede_id" name="sede_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                <option value="">— sin sede —</option>
                                @foreach ($sedes as $sede)
                                    <option value="{{ $sede->id }}" @selected(old('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-tinta-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-tinta-700">
                            Crear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
