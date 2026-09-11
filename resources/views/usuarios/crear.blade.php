<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Crear usuario
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />
            <x-validation-errors class="mb-4" />

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('usuarios.store') }}" class="space-y-6"
                      x-data="{ tipo: '{{ old('tipo', 'trabajador') }}' }">
                    @csrf

                    @if ($esJefeDeArea)
                        <div>
                            <x-label for="tipo" value="¿Qué vas a crear?" />
                            <select id="tipo" name="tipo" x-model="tipo" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Selecciona una unidad de tu área</option>
                                @foreach ($unidades as $unidad)
                                    <option value="{{ $unidad->id }}" @selected(old('unidad_organica_id') == $unidad->id)>{{ $unidad->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="rounded-md bg-indigo-50 border border-indigo-200 p-4 text-sm text-indigo-800">
                            Este trabajador quedará bajo tu supervisión: te asignaremos automáticamente como su jefe inmediato.
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
                            <select id="regimen" name="regimen" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Selecciona</option>
                                <option value="276" @selected(old('regimen') === '276')>276 (día)</option>
                                <option value="728" @selected(old('regimen') === '728')>728 (rotativo)</option>
                            </select>
                            <x-input-error for="regimen" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-label for="email" value="Correo" />
                        <x-input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full" />
                        <x-input-error for="email" class="mt-2" />
                    </div>

                    <div>
                        <x-label for="password" value="Contraseña inicial" />
                        <x-input id="password" name="password" type="text" minlength="8" required class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500">El usuario podrá cambiarla luego desde su perfil.</p>
                        <x-input-error for="password" class="mt-2" />
                    </div>

                    <div>
                        <x-label for="sede_id" value="Sede (opcional)" />
                        <select id="sede_id" name="sede_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">— sin sede —</option>
                            @foreach ($sedes as $sede)
                                <option value="{{ $sede->id }}" @selected(old('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Crear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
