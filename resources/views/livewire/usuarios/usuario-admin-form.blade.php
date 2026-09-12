<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            {{ $usuario ? 'Editar usuario' : 'Nuevo usuario' }}
        </h2>

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nombres</label>
                    <input type="text" wire:model="name" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Apellidos</label>
                    <input type="text" wire:model="apellido" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('apellido') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">DNI</label>
                    <input type="text" wire:model="dni" maxlength="8" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('dni') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Correo</label>
                    <input type="email" wire:model="email" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Contraseña</label>
                    <input type="password" wire:model="password" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        @if ($usuario) Déjala en blanco para no cambiar la contraseña actual. @endif
                    </p>
                    @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Régimen</label>
                    <select wire:model="regimen" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— Selecciona —</option>
                        <option value="276">276 (día)</option>
                        <option value="728">728 (rotativo)</option>
                    </select>
                    @error('regimen') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Sede</label>
                    <select wire:model="sedeId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— (ninguna) —</option>
                        @foreach ($sedes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('sedeId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Unidad orgánica</label>
                    <select wire:model="unidadOrganicaId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— (ninguna) —</option>
                        @foreach ($unidades as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Determina automáticamente su jefe inmediato y jefe de área.</p>
                    @error('unidadOrganicaId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <label class="block text-sm font-medium mb-2">Rol(es)</label>
                <div class="flex flex-wrap gap-4">
                    @foreach ($roles as $rol)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="rolesSeleccionados" value="{{ $rol->id }}" class="rounded">
                            <span class="text-sm">{{ $rol->name }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    admin y rrhh son roles globales. "trabajador" es el rol de todos los demás (incluye a quienes además son Jefe Inmediato o Jefe de Área por posición en el organigrama).
                </p>
                @error('rolesSeleccionados') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('usuarios-admin.index') }}" class="text-sm text-gray-500">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
