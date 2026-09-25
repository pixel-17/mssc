<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
            Editar usuario
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-validation-errors class="mb-4" />

            <div class="glass-card p-6">
                <form method="POST" action="{{ route('usuarios.update', $trabajador) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-tinta-100/50 uppercase">DNI</p>
                            <p class="text-gray-900 dark:text-white">{{ $trabajador->dni }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-tinta-100/50 uppercase">Régimen</p>
                            <p class="text-gray-900 dark:text-white">{{ $trabajador->regimen }}</p>
                        </div>
                    </div>

                    @unless ($esJefeDeArea)
                        <div class="rounded-md bg-tinta-50 border border-tinta-200 p-4 text-sm text-tinta-800">
                            Sede y unidad orgánica se mantienen iguales a las tuyas (no se pueden editar aquí).
                        </div>
                    @endunless

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-label for="name" value="Nombres" />
                            <x-input id="name" name="name" type="text" value="{{ old('name', $trabajador->name) }}" required class="mt-1 block w-full" />
                            <x-input-error for="name" class="mt-2" />
                        </div>
                        <div>
                            <x-label for="apellido" value="Apellidos" />
                            <x-input id="apellido" name="apellido" type="text" value="{{ old('apellido', $trabajador->apellido) }}" required class="mt-1 block w-full" />
                            <x-input-error for="apellido" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-label for="email" value="Correo" />
                        <x-input id="email" name="email" type="email" value="{{ old('email', $trabajador->email) }}" required class="mt-1 block w-full" />
                        <x-input-error for="email" class="mt-2" />
                    </div>

                    @if ($trabajador->regimen === '728')
                        <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-3 flex items-center justify-between gap-3">
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                El turno (ciclo, días de trabajo/descanso) se gestiona en su propia pantalla, no aquí.
                            </p>
                            <a href="{{ route('turnos.configuracion', $trabajador) }}"
                               class="shrink-0 text-xs font-semibold text-tinta-600 hover:text-tinta-800 uppercase tracking-widest">
                                Configurar turno
                            </a>
                        </div>
                    @endif

                    @if ($esJefeDeArea)
                        <div>
                            <x-label for="unidad_organica_id" value="Unidad orgánica" />
                            <select id="unidad_organica_id" name="unidad_organica_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                @foreach ($unidades as $unidad)
                                    <option value="{{ $unidad->id }}" @selected(old('unidad_organica_id', $trabajador->unidad_organica_id) == $unidad->id)>{{ $unidad->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="unidad_organica_id" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="sede_id" value="Sede (opcional)" />
                            <select id="sede_id" name="sede_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-tinta-500 focus:ring-tinta-500 sm:text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                <option value="">— sin sede —</option>
                                @foreach ($sedes as $sede)
                                    <option value="{{ $sede->id }}" @selected(old('sede_id', $trabajador->sede_id) == $sede->id)>{{ $sede->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-tinta-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-tinta-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
