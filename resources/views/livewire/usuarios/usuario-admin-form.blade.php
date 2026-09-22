<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
            {{ $usuario ? 'Editar usuario' : 'Nuevo usuario' }}
        </h2>

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="usuario-admin-form-name" class="block text-sm font-medium mb-1">Nombres</label>
                    <input id="usuario-admin-form-name" type="text" wire:model="name" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="usuario-admin-form-apellido" class="block text-sm font-medium mb-1">Apellidos</label>
                    <input id="usuario-admin-form-apellido" type="text" wire:model="apellido" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('apellido') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="usuario-admin-form-dni" class="block text-sm font-medium mb-1">DNI</label>
                    <input id="usuario-admin-form-dni" type="text" wire:model="dni" maxlength="8" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('dni') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="usuario-admin-form-email" class="block text-sm font-medium mb-1">Correo</label>
                    <input id="usuario-admin-form-email" type="email" wire:model="email" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                @if ($usuario)
                    <div>
                        <label for="usuario-admin-form-password" class="block text-sm font-medium mb-1">Contraseña (opcional)</label>
                        <input id="usuario-admin-form-password" type="password" wire:model="password" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Déjala en blanco para no cambiarla. Si la llenas, se le pedirá actualizarla en su próximo ingreso.
                        </p>
                        @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                @else
                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Su contraseña inicial será su DNI. El sistema le pedirá actualizarla
                            (de forma opcional) la primera vez que ingrese.
                        </p>
                    </div>
                @endif

                <div>
                    <label for="usuario-admin-form-regimen" class="block text-sm font-medium mb-1">Régimen</label>
                    <select id="usuario-admin-form-regimen" wire:model.live="regimen" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— Selecciona —</option>
                        <option value="276">276 (día)</option>
                        <option value="728">728 (rotativo)</option>
                    </select>
                    @error('regimen') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="usuario-admin-form-sedeId" class="block text-sm font-medium mb-1">Sede</label>
                    <select id="usuario-admin-form-sedeId" wire:model="sedeId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— (ninguna) —</option>
                        @foreach ($sedes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('sedeId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="usuario-admin-form-unidadOrganicaId" class="block text-sm font-medium mb-1">Unidad orgánica</label>
                    <select id="usuario-admin-form-unidadOrganicaId" wire:model="unidadOrganicaId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">— (ninguna) —</option>
                        @foreach ($unidades as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Determina automáticamente su jefe inmediato y jefe de área.</p>
                    @error('unidadOrganicaId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            @if ($requiereTurno)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p class="text-sm font-medium mb-1">Turno inicial (régimen 728)</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Este trabajador todavía no tiene un horario cargado. Sin esto no podrá crear ninguna
                        papeleta desde el primer día (ver régimen 728 en Turnos). Trabajará
                        {{ $diasTrabajo }} días seguidos en el turno elegido y descansará {{ $diasDescanso }},
                        repitiendo el ciclo. Si no se vuelve a cargar una actualización, el mes siguiente se
                        genera solo con esta misma configuración.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="usuario-admin-form-turno" class="block text-sm font-medium mb-1">Turno</label>
                            <select id="usuario-admin-form-turno" wire:model="turno" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                                <option value="">— Selecciona —</option>
                                @foreach ($opcionesTurno as $opcion)
                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                @endforeach
                            </select>
                            @error('turno') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="usuario-admin-form-fechaAncla" class="block text-sm font-medium mb-1">Fecha en que empieza su próximo bloque de trabajo</label>
                            <input id="usuario-admin-form-fechaAncla" type="date" wire:model="fechaAncla" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                            @error('fechaAncla') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="usuario-admin-form-diasTrabajo" class="block text-sm font-medium mb-1">Días de trabajo seguidos</label>
                            <input id="usuario-admin-form-diasTrabajo" type="number" min="1" max="30" wire:model="diasTrabajo" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                            @error('diasTrabajo') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="usuario-admin-form-diasDescanso" class="block text-sm font-medium mb-1">Días de descanso</label>
                            <input id="usuario-admin-form-diasDescanso" type="number" min="1" max="30" wire:model="diasDescanso" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                            @error('diasDescanso') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="activo" class="rounded">
                    <span class="text-sm font-medium">Activo</span>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Desmárcalo si el trabajador se retiró, cesó o está de licencia larga: el generador automático
                    de turnos deja de crearle horario para los próximos meses (su configuración de turno no se
                    borra, solo queda pausada).
                </p>
                @error('activo') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p id="usuario-admin-form-roles-titulo" class="block text-sm font-medium mb-2">Rol(es)</p>
                <div class="flex flex-wrap gap-4" role="group" aria-labelledby="usuario-admin-form-roles-titulo">
                    @foreach ($roles as $rol)
                        <label wire:key="usuario-admin-form-label-{{ $rol->id }}" class="flex items-center gap-2">
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
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-tinta-800 text-white rounded-md text-sm">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
