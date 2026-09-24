<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
            Nueva oficina de mi área
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-validation-errors class="mb-4" />

            @if (session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <div class="glass-card p-6"
                 x-data="{
                    regimen: '{{ old('jefes.0.regimen', '276') }}',
                    cobertura: 'mismo',
                    turnoActivo: { MANANA: true, TARDE: false, NOCHE: false },
                 }">
                <form method="POST" action="{{ route('unidad-organica.oficinas.store') }}" class="space-y-8">
                    @csrf

                    {{-- Paso 1: la oficina --}}
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-tinta-800 dark:text-tinta-200">1. Datos de la oficina</h3>

                        <div>
                            <x-label for="nombre" value="Nombre de la oficina" />
                            <x-input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" required class="mt-1 block w-full" />
                            <x-input-error for="nombre" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-label for="tipo" value="Tipo (opcional, solo visual)" />
                                <select id="tipo" name="tipo"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                    <option value="">— sin tipo —</option>
                                    @foreach ($tipos as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label for="parent_id" value="Cuelga de" />
                                <select id="parent_id" name="parent_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                    <option value="">Selecciona una unidad de tu área</option>
                                    @foreach ($unidadesPadre as $id => $nombre)
                                        <option value="{{ $id }}" @selected(old('parent_id') == $id)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Solo se listan unidades dentro de tu propia área.</p>
                                <x-input-error for="parent_id" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    {{-- Paso 2: régimen y cobertura --}}
                    <div class="space-y-4 border-t border-gray-200 dark:border-white/10 pt-6">
                        <h3 class="text-sm font-semibold text-tinta-800 dark:text-tinta-200">2. Régimen de la oficina</h3>

                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="regimen" value="276" class="rounded-full">
                                276 (día) — un solo jefe
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="regimen" value="728" class="rounded-full">
                                728 (rotativo)
                            </label>
                        </div>

                        <div x-show="regimen === '728'" x-cloak class="pl-1 space-y-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="cobertura" value="mismo" class="rounded-full">
                                Un jefe cubre los 3 turnos
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="cobertura" value="por_turno" class="rounded-full">
                                Jefe distinto por turno
                            </label>
                        </div>
                    </div>

                    {{-- Paso 3: jefe(s) --}}
                    <div class="space-y-6 border-t border-gray-200 dark:border-white/10 pt-6">
                        <h3 class="text-sm font-semibold text-tinta-800 dark:text-tinta-200">3. Jefe(s) inmediato(s)</h3>

                        {{-- Caso 276, o 728 con un solo jefe: un único bloque, índice 0 --}}
                        <template x-if="regimen === '276' || cobertura === 'mismo'">
                            <div class="rounded-md border border-dashed border-gray-300 dark:border-white/15 p-4 space-y-4">
                                <input type="hidden" name="jefes[0][regimen]" :value="regimen">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-label value="Nombres" />
                                        <x-input name="jefes[0][name]" type="text" value="{{ old('jefes.0.name') }}" required class="mt-1 block w-full" />
                                        <x-input-error for="jefes.0.name" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-label value="Apellidos" />
                                        <x-input name="jefes[0][apellido]" type="text" value="{{ old('jefes.0.apellido') }}" required class="mt-1 block w-full" />
                                        <x-input-error for="jefes.0.apellido" class="mt-2" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-label value="DNI" />
                                        <x-input name="jefes[0][dni]" type="text" maxlength="8" value="{{ old('jefes.0.dni') }}" required class="mt-1 block w-full" />
                                        <x-input-error for="jefes.0.dni" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-label value="Correo" />
                                        <x-input name="jefes[0][email]" type="email" value="{{ old('jefes.0.email') }}" required class="mt-1 block w-full" />
                                        <x-input-error for="jefes.0.email" class="mt-2" />
                                    </div>
                                </div>

                                <div x-show="regimen === '728'" x-cloak class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-label value="Turno" />
                                        <select name="jefes[0][turno]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                            <option value="">Selecciona</option>
                                            <option value="MANANA" @selected(old('jefes.0.turno') === 'MANANA')>MAÑANA</option>
                                            <option value="TARDE" @selected(old('jefes.0.turno') === 'TARDE')>TARDE</option>
                                            <option value="NOCHE" @selected(old('jefes.0.turno') === 'NOCHE')>NOCHE</option>
                                        </select>
                                        <x-input-error for="jefes.0.turno" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-label value="Empieza su próximo bloque de trabajo" />
                                        <x-input name="jefes[0][fecha_ancla]" type="date" value="{{ old('jefes.0.fecha_ancla', now()->toDateString()) }}" class="mt-1 block w-full" />
                                        <x-input-error for="jefes.0.fecha_ancla" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-label value="Días de trabajo seguidos" />
                                        <x-input name="jefes[0][dias_trabajo]" type="number" min="1" max="30" value="{{ old('jefes.0.dias_trabajo', 6) }}" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <x-label value="Días de descanso" />
                                        <x-input name="jefes[0][dias_descanso]" type="number" min="1" max="30" value="{{ old('jefes.0.dias_descanso', 1) }}" class="mt-1 block w-full" />
                                    </div>
                                </div>

                                <div>
                                    <x-label value="Sede (opcional)" />
                                    <select name="jefes[0][sede_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                        <option value="">— sin sede —</option>
                                        @foreach ($sedes as $sede)
                                            <option value="{{ $sede->id }}" @selected(old('jefes.0.sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </template>

                        {{-- Caso 728 por turno: hasta 3 bloques, uno por turno, con checkbox para activarlo --}}
                        <template x-if="regimen === '728' && cobertura === 'por_turno'">
                            <div class="space-y-4">
                                <template x-for="(turno, indice) in ['MANANA', 'TARDE', 'NOCHE']" :key="turno">
                                    <div class="rounded-md border border-dashed border-gray-300 dark:border-white/15 p-4 space-y-4">
                                        <label class="flex items-center gap-2 text-sm font-medium">
                                            <input type="checkbox" :checked="turnoActivo[turno]" @change="turnoActivo[turno] = $event.target.checked" class="rounded">
                                            <span x-text="turno === 'MANANA' ? 'Mañana' : (turno === 'TARDE' ? 'Tarde' : 'Noche')"></span>
                                        </label>

                                        <div x-show="turnoActivo[turno]" x-cloak class="space-y-4">
                                            <input type="hidden" :name="`jefes[${indice}][regimen]`" value="728">
                                            <input type="hidden" :name="`jefes[${indice}][turno]`" :value="turno">

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <x-label value="Nombres" />
                                                    <input type="text" :name="`jefes[${indice}][name]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                                </div>
                                                <div>
                                                    <x-label value="Apellidos" />
                                                    <input type="text" :name="`jefes[${indice}][apellido]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <x-label value="DNI" />
                                                    <input type="text" maxlength="8" :name="`jefes[${indice}][dni]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                                </div>
                                                <div>
                                                    <x-label value="Correo" />
                                                    <input type="email" :name="`jefes[${indice}][email]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <x-label value="Empieza su próximo bloque de trabajo" />
                                                    <input type="date" :name="`jefes[${indice}][fecha_ancla]`" value="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                                </div>
                                                <div>
                                                    <x-label value="Sede (opcional)" />
                                                    <select :name="`jefes[${indice}][sede_id]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                                        <option value="">— sin sede —</option>
                                                        @foreach ($sedes as $sede)
                                                            <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <x-input-error for="jefes" class="mt-2" />
                            </div>
                        </template>
                    </div>

                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            La contraseña inicial de cada jefe será su DNI. Se le pedirá actualizarla (opcional) al ingresar por primera vez.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-tinta-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-tinta-700">
                            Crear oficina
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
