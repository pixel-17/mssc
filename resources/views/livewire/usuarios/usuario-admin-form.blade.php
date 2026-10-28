<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$usuario ? 'Editar usuario' : 'Nuevo administrador / RR. HH.'" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-admin.campo label="Nombres" for="usuario-admin-form-name" campo="name">
                    <x-input id="usuario-admin-form-name" type="text" wire:model="name" class="w-full" />
                </x-admin.campo>

                <x-admin.campo label="Apellidos" for="usuario-admin-form-apellido" campo="apellido">
                    <x-input id="usuario-admin-form-apellido" type="text" wire:model="apellido" class="w-full" />
                </x-admin.campo>

                <x-admin.campo label="DNI" for="usuario-admin-form-dni" campo="dni">
                    <x-input id="usuario-admin-form-dni" type="text" wire:model="dni" maxlength="8" class="w-full" />
                </x-admin.campo>

                <x-admin.campo label="Correo" for="usuario-admin-form-email" campo="email">
                    <x-input id="usuario-admin-form-email" type="email" wire:model="email" class="w-full" />
                </x-admin.campo>

                @if ($usuario)
                    <x-admin.campo
                        label="Contraseña (opcional)"
                        for="usuario-admin-form-password"
                        campo="password"
                        ayuda="Déjala en blanco para no cambiarla. Si la llenas, se le pedirá actualizarla en su próximo ingreso."
                    >
                        <x-input id="usuario-admin-form-password" type="password" wire:model="password" class="w-full" />
                    </x-admin.campo>
                @else
                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Su contraseña inicial será su DNI. El sistema le pedirá actualizarla
                            (de forma opcional) la primera vez que ingrese.
                        </p>
                    </div>
                @endif

                {{-- Régimen, sede y unidad solo aplican en edición y solo cuando el usuario pertenece al organigrama. --}}
                @if ($usuario && !$esAdminRol && !$esSoloRrhhRol)
                    <x-admin.campo label="Régimen" for="usuario-admin-form-regimen" campo="regimen">
                        <x-select id="usuario-admin-form-regimen" wire:model.live="regimen">
                            <option value="">— Selecciona —</option>
                            <option value="276">276 (día)</option>
                            <option value="728">728 (rotativo)</option>
                        </x-select>
                    </x-admin.campo>

                    <x-admin.campo label="Sede" for="usuario-admin-form-sedeId" campo="sedeId">
                        <x-organigrama.combobox id="usuario-admin-form-sedeId" model="sedeId" :options="$sedes" placeholder="Buscar sede…" vacio="— Selecciona —" />
                    </x-admin.campo>

                    <x-admin.campo
                        label="Unidad orgánica"
                        for="usuario-admin-form-unidadOrganicaId"
                        campo="unidadOrganicaId"
                        ayuda="Determina automáticamente su jefe inmediato y jefe de área."
                    >
                        <x-organigrama.combobox id="usuario-admin-form-unidadOrganicaId" model="unidadOrganicaId" :options="$unidades" placeholder="Buscar unidad…" vacio="— Selecciona —" />
                    </x-admin.campo>
                @endif
            </div>

            @if ($cuentaExistente)
                <div
                    class="rounded-md border border-yellow-300 bg-yellow-50 dark:border-yellow-700 dark:bg-yellow-900/30 px-4 py-3"
                    role="alert"
                >
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        Ya existe una cuenta con el DNI {{ $cuentaExistente->dni }}:
                        {{ $cuentaExistente->name }} {{ $cuentaExistente->apellido }}
                        ({{ $cuentaExistente->activo ? 'activo' : 'desactivado' }}).

                        @unless ($cuentaExistente->activo)
                            Para volver a darle acceso, ábrelo y marca «Activo».
                        @endunless
                    </p>

                    <a
                        href="{{ route('usuarios-admin.editar', $cuentaExistente) }}"
                        class="mt-1 inline-block text-sm font-medium text-yellow-900 dark:text-yellow-100 underline"
                    >
                        Ver usuario
                    </a>
                </div>
            @endif

            @if ($esAdminRol)
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Un administrador gestiona cuentas y catálogos. No pertenece a ninguna sede ni unidad
                    del organigrama.
                </p>
            @endif

            @if ($requiereTurno)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p class="text-sm font-medium mb-1">Turno inicial (régimen 728, opcional)</p>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Este trabajador todavía no tiene un horario cargado. Puedes cargarlo ahora o después;
                        hasta entonces no podrá crear papeletas. Trabajará {{ $diasTrabajo }} días seguidos
                        en el turno elegido y descansará {{ $diasDescanso }}, repitiendo el ciclo.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-admin.campo label="Turno" for="usuario-admin-form-turno" campo="turno">
                            <x-select id="usuario-admin-form-turno" wire:model="turno">
                                <option value="">— Selecciona —</option>
                                @foreach ($opcionesTurno as $opcion)
                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                @endforeach
                            </x-select>
                        </x-admin.campo>

                        <x-admin.campo
                            label="Fecha en que empieza su próximo bloque de trabajo"
                            for="usuario-admin-form-fechaAncla"
                            campo="fechaAncla"
                        >
                            <x-input
                                id="usuario-admin-form-fechaAncla"
                                type="date"
                                wire:model="fechaAncla"
                                class="w-full"
                            />
                        </x-admin.campo>

                        <x-admin.campo
                            label="Días de trabajo seguidos"
                            for="usuario-admin-form-diasTrabajo"
                            campo="diasTrabajo"
                        >
                            <x-input
                                id="usuario-admin-form-diasTrabajo"
                                type="number"
                                min="1"
                                max="30"
                                wire:model="diasTrabajo"
                                class="w-full"
                            />
                        </x-admin.campo>

                        <x-admin.campo
                            label="Días de descanso"
                            for="usuario-admin-form-diasDescanso"
                            campo="diasDescanso"
                        >
                            <x-input
                                id="usuario-admin-form-diasDescanso"
                                type="number"
                                min="1"
                                max="30"
                                wire:model="diasDescanso"
                                class="w-full"
                            />
                        </x-admin.campo>
                    </div>
                </div>
            @endif

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <x-admin.campo-checkbox
                    for="activo"
                    wire:model="activo"
                    label="Activo"
                    ayuda="Desmárcalo si el trabajador se retiró, cesó o está de licencia larga."
                />
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p id="usuario-admin-form-roles-titulo" class="block text-sm font-medium mb-2">
                    Rol
                </p>

                @if ($usuario)
                    {{-- Edición: checkboxes para poder combinar roles --}}
                    <div
                        class="flex flex-wrap gap-4"
                        role="group"
                        aria-labelledby="usuario-admin-form-roles-titulo"
                    >
                        @foreach ($roles as $rol)
                            <label
                                wire:key="usuario-admin-form-label-{{ $rol->id }}"
                                class="flex items-center gap-2"
                            >
                                <x-checkbox
                                    wire:model.live="rolesSeleccionados"
                                    value="{{ $rol->id }}"
                                />
                                <span class="text-sm">{{ $rol->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    {{-- Alta nueva: radio para elegir uno solo (admin o rrhh) --}}
                    <div
                        class="flex flex-wrap gap-4"
                        role="radiogroup"
                        aria-labelledby="usuario-admin-form-roles-titulo"
                    >
                        @foreach ($roles as $rol)
                            <label
                                wire:key="usuario-admin-form-label-{{ $rol->id }}"
                                class="flex items-center gap-2 cursor-pointer"
                            >
                                <input
                                    type="radio"
                                    name="rol_seleccionado"
                                    value="{{ $rol->id }}"
                                    wire:model.live="rolNuevo"
                                    class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="text-sm">{{ $rol->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Elige un solo rol. Si la persona también es trabajador, dála de alta desde el organigrama.
                    </p>
                @endif

                <x-input-error for="rolesSeleccionados" class="mt-2" />
            </div>

            <x-admin.barra-flotante>
                <a
                    href="{{ route('usuarios-admin.index') }}"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >
                    Cancelar
                </a>
                <x-button>Guardar</x-button>
            </x-admin.barra-flotante>
        </form>
    </div>
</div>