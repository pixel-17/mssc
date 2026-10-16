@php
    $esAdmin  = $alcance === 'admin';
    $esArea   = $alcance === 'area';
    $esEquipo = $alcance === 'equipo';
    $creando  = $usuario === null;
    $cancelar = $esAdmin ? route('usuarios-admin.index') : route('usuarios.index');
@endphp

<div>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado :titulo="$usuario ? 'Editar usuario' : ($nuevoAdmin ? 'Nuevo administrador' : 'Nuevo usuario')" />

        <form wire:submit="guardar" class="glass-card p-6 space-y-4">

            {{-- Jefe de Área: qué tipo de usuario va a crear --}}
            @if ($esArea && $creando)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-admin.campo label="¿Qué vas a crear?" for="usuario-form-tipo" campo="tipo">
                        <x-select id="usuario-form-tipo" wire:model.live="tipo">
                            <option value="trabajador">Trabajador</option>
                            <option value="jefe_inmediato">Jefe Inmediato</option>
                        </x-select>
                    </x-admin.campo>
                </div>
            @endif

            {{-- Jefe Inmediato: aviso de herencia --}}
            @if ($esEquipo && $creando)
                <div class="rounded-xl border border-tinta-200 bg-tinta-50 p-4 text-sm text-tinta-800 dark:border-white/10 dark:bg-white/5 dark:text-tinta-100">
                    Este trabajador quedará bajo tu supervisión y heredará tu misma unidad y sede{{ $sedeDelCreador ? ' ('.$sedeDelCreador.')' : '' }}.
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-admin.campo label="Nombres" for="usuario-form-name" campo="name">
                    <x-input id="usuario-form-name" type="text" wire:model="name" class="w-full" />
                </x-admin.campo>

                <x-admin.campo label="Apellidos" for="usuario-form-apellido" campo="apellido">
                    <x-input id="usuario-form-apellido" type="text" wire:model="apellido" class="w-full" />
                </x-admin.campo>

                {{-- DNI: solo admin o al crear (el Jefe Inmediato no lo edita) --}}
                @if ($creando || !$esEquipo)
                    <x-admin.campo label="DNI" for="usuario-form-dni" campo="dni">
                        <x-input id="usuario-form-dni" type="text" wire:model="dni" maxlength="8" inputmode="numeric" class="w-full" />
                    </x-admin.campo>
                @else
                    <div>
                        <p class="text-xs text-gray-500 dark:text-tinta-100/50 uppercase tracking-wide">DNI</p>
                        <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $usuario->dni }}</p>
                    </div>
                @endif

                <x-admin.campo label="Correo" for="usuario-form-email" campo="email">
                    <x-input id="usuario-form-email" type="email" wire:model="email" class="w-full" />
                </x-admin.campo>

                {{-- Contraseña: solo admin al editar --}}
                @if ($esAdmin && $usuario)
                    <x-admin.campo
                        label="Contraseña (opcional)"
                        for="usuario-form-password" campo="password"
                        ayuda="Déjala en blanco para no cambiarla. Si la llenas, se le pedirá actualizarla en su próximo ingreso."
                    >
                        <x-input id="usuario-form-password" type="password" wire:model="password" class="w-full" />
                    </x-admin.campo>
                @elseif ($esAdmin && $creando)
                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Su contraseña inicial será su DNI. El sistema le pedirá actualizarla (de forma opcional) la primera vez que ingrese.
                        </p>
                    </div>
                @endif

                {{-- Régimen: no lo ve el Jefe Inmediato al editar --}}
                @if (!$esEquipo || $creando)
                    <x-admin.campo
                        label="Régimen"
                        for="usuario-form-regimen" campo="regimen"
                        :ayuda="$esAdmin ? 'Opcional solo si el usuario es únicamente administrador.' : ''"
                    >
                        @if ($esEquipo)
                            {{-- Jefe Inmediato creando: el régimen se hereda, solo se muestra --}}
                            <x-select id="usuario-form-regimen" wire:model.live="regimen" disabled>
                                <option value="">— Selecciona —</option>
                                <option value="276">276 (día)</option>
                                <option value="728">728 (rotativo)</option>
                            </x-select>
                            <p class="text-xs text-gray-500 dark:text-tinta-100/60 mt-1">Debe coincidir con el tuyo.</p>
                        @else
                            <x-select id="usuario-form-regimen" wire:model.live="regimen">
                                <option value="">— Selecciona —</option>
                                <option value="276">276 (día)</option>
                                <option value="728">728 (rotativo)</option>
                            </x-select>
                        @endif
                    </x-admin.campo>
                @endif

                {{-- Sede --}}
                @if ($esAdmin)
                    <x-admin.campo label="Sede" for="usuario-form-sedeId" campo="sedeId">
                        <x-select id="usuario-form-sedeId" wire:model="sedeId">
                            <option value="">— (ninguna) —</option>
                            @foreach ($sedes as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </x-select>
                    </x-admin.campo>
                @elseif ($esArea && (!$creando || $tipo === 'jefe_inmediato'))
                    <x-admin.campo label="Sede" for="usuario-form-sedeId" campo="sedeId">
                        <x-select id="usuario-form-sedeId" wire:model="sedeId">
                            <option value="">— Selecciona una sede —</option>
                            @foreach ($sedes as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </x-select>
                    </x-admin.campo>
                @elseif ($esArea && $creando)
                    {{-- Trabajador nuevo: hereda la sede del jefe de la unidad --}}
                    <div class="rounded-xl border border-tinta-100 bg-tinta-50/60 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-tinta-50/60">Sede del trabajador</p>
                        <p class="mt-0.5 text-sm font-medium text-tinta-950 dark:text-white">{{ $sedeHeredada ?? 'Elige primero la unidad' }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-tinta-100/60">Se asigna sola: es la sede del jefe inmediato de la unidad elegida.</p>
                    </div>
                @endif

                {{-- Unidad orgánica --}}
                @if ($esAdmin && !$esAdminRol)
                    <x-admin.campo label="Unidad orgánica" for="usuario-form-unidadOrganicaId" campo="unidadOrganicaId"
                        ayuda="Determina automáticamente su jefe inmediato y jefe de área.">
                        <x-select id="usuario-form-unidadOrganicaId" wire:model="unidadOrganicaId">
                            <option value="">— (ninguna) —</option>
                            @foreach ($unidades as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </x-select>
                    </x-admin.campo>
                @elseif ($esArea)
                    <x-admin.campo label="Unidad" for="usuario-form-unidadOrganicaId" campo="unidadOrganicaId"
                        ayuda="Solo las unidades de tu área.">
                        <x-select id="usuario-form-unidadOrganicaId" wire:model.live="unidadOrganicaId">
                            <option value="">— Selecciona una unidad —</option>
                            @foreach ($unidades as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </x-select>
                    </x-admin.campo>
                @endif
            </div>

            {{-- Aviso de cuenta existente con ese DNI (solo admin al crear) --}}
            @if ($cuentaExistente)
                <div class="rounded-md border border-yellow-300 bg-yellow-50 dark:border-yellow-700 dark:bg-yellow-900/30 px-4 py-3" role="alert">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        Ya existe una cuenta con el DNI {{ $cuentaExistente->dni }}:
                        {{ $cuentaExistente->name }} {{ $cuentaExistente->apellido }}
                        ({{ $cuentaExistente->activo ? 'activo' : 'desactivado' }}).
                        @unless ($cuentaExistente->activo)
                            Para volver a darle acceso, ábrelo y marca «Activo».
                        @endunless
                    </p>
                    <a href="{{ route('usuarios.editar', $cuentaExistente) }}"
                       class="mt-1 inline-block text-sm font-medium text-yellow-900 dark:text-yellow-100 underline">
                        Ver usuario
                    </a>
                </div>
            @endif

            @if ($esAdminRol)
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Un administrador gestiona cuentas y catálogos: no pertenece a ninguna unidad del organigrama ni tiene equipo.
                </p>
            @endif

            {{-- Turno (admin y jefe de área al editar un 728 sin turno) --}}
            @if ($requiereTurno)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p class="text-sm font-medium mb-1">Turno inicial (régimen 728, opcional)</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Este trabajador todavía no tiene un horario cargado. Puedes cargarlo ahora o después;
                        hasta entonces no podrá crear papeletas. Trabajará {{ $diasTrabajo }} días seguidos y
                        descansará {{ $diasDescanso }}, repitiendo el ciclo.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-admin.campo label="Turno" for="usuario-form-turno" campo="turno">
                            <x-select id="usuario-form-turno" wire:model="turno">
                                <option value="">— Selecciona —</option>
                                @foreach ($opcionesTurno as $opcion)
                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                @endforeach
                            </x-select>
                        </x-admin.campo>

                        <x-admin.campo label="Fecha inicio del próximo bloque" for="usuario-form-fechaAncla" campo="fechaAncla">
                            <x-input id="usuario-form-fechaAncla" type="date" wire:model="fechaAncla" class="w-full" />
                        </x-admin.campo>

                        <x-admin.campo label="Días de trabajo seguidos" for="usuario-form-diasTrabajo" campo="diasTrabajo">
                            <x-input id="usuario-form-diasTrabajo" type="number" min="1" max="30" wire:model="diasTrabajo" class="w-full" />
                        </x-admin.campo>

                        <x-admin.campo label="Días de descanso" for="usuario-form-diasDescanso" campo="diasDescanso">
                            <x-input id="usuario-form-diasDescanso" type="number" min="1" max="30" wire:model="diasDescanso" class="w-full" />
                        </x-admin.campo>
                    </div>
                </div>
            @endif

            {{-- Activo: admin siempre; jefe de área y jefe inmediato solo al editar --}}
            @if ($esAdmin || (($esArea || $esEquipo) && $usuario))
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <x-admin.campo-checkbox
                        for="activo"
                        wire:model="activo"
                        label="Activo"
                        ayuda="Desmárcalo si el trabajador se retiró o está de licencia larga: el generador deja de crearle horario (la configuración de turno no se borra, queda pausada)."
                    />
                </div>
            @endif

            {{-- Roles: solo admin --}}
            @if ($esAdmin)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p id="usuario-form-roles-titulo" class="block text-sm font-medium mb-2">Rol(es)</p>
                    <div class="flex flex-wrap gap-4" role="group" aria-labelledby="usuario-form-roles-titulo">
                        @foreach ($roles as $rol)
                            <label wire:key="usuario-form-label-{{ $rol->id }}" class="flex items-center gap-2">
                                <x-checkbox wire:model.live="rolesSeleccionados" value="{{ $rol->id }}" />
                                <span class="text-sm">{{ $rol->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        admin y rrhh son roles globales. «trabajador» es el rol de todos los demás.
                    </p>
                    <x-input-error for="rolesSeleccionados" class="mt-2" />
                </div>
            @endif

            <x-admin.barra-flotante>
                <a href="{{ $cancelar }}" class="text-sm text-gray-600 dark:text-gray-400">Cancelar</a>
                <x-button>Guardar</x-button>
            </x-admin.barra-flotante>
        </form>
    </div>
</div>