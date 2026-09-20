<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 dark:text-white leading-tight tracking-tight">
                Usuarios
            </h2>

            <a href="{{ route('usuarios-admin.crear') }}" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                + Nuevo usuario
            </a>
        </div>

        @if (session('mensaje'))
            <div class="glass-card p-4 text-sm text-green-700 dark:text-green-400">
                {{ session('mensaje') }}
            </div>
        @endif

        @if (session('error'))
            <div class="glass-card p-4 text-sm text-red-700 dark:text-red-400" role="alert">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex flex-wrap gap-4">
            <div class="max-w-xs flex-1">
                <label class="block text-sm font-medium mb-1">Buscar</label>
                <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Nombre, apellido, DNI o correo" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
            </div>

            <div class="max-w-xs">
                <label class="block text-sm font-medium mb-1">Régimen</label>
                <select wire:model.live="regimen" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    <option value="">Todos</option>
                    <option value="276">276 (día)</option>
                    <option value="728">728 (rotativo)</option>
                </select>
            </div>

            <div class="max-w-xs">
                <label class="block text-sm font-medium mb-1">Rol</label>
                <select wire:model.live="rolId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                    <option value="">Todos</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id }}">{{ $rol->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="glass-card overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">DNI</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Régimen</th>
                        <th class="px-4 py-3">Unidad</th>
                        <th class="px-4 py-3">Rol(es)</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($usuarios as $usuario)
                        <tr wire:key="usuario-{{ $usuario->id }}">
                            <td class="px-4 py-3">{{ $usuario->name }} {{ $usuario->apellido }}</td>
                            <td class="px-4 py-3">{{ $usuario->dni }}</td>
                            <td class="px-4 py-3">{{ $usuario->email }}</td>
                            <td class="px-4 py-3">{{ $usuario->regimen }}</td>
                            <td class="px-4 py-3">{{ $usuario->unidadOrganica?->nombre ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $usuario->roles->pluck('name')->join(', ') }}</td>
                            <td class="px-4 py-3">
                                @if ($usuario->activo)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        Inactivo
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('turnos.calendario.individual-de', $usuario) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Calendario
                                </a>
                                <a href="{{ route('usuarios-admin.editar', $usuario) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Editar
                                </a>
                                @if ($usuario->activo)
                                    <button
                                        type="button"
                                        wire:click="desactivar({{ $usuario->id }})"
                                        wire:confirm="¿Desactivar a {{ $usuario->name }} {{ $usuario->apellido }} (DNI {{ $usuario->dni }})? No podrá iniciar sesión hasta que lo reactives."
                                        class="text-sm text-red-600 dark:text-red-400 underline"
                                    >
                                        Desactivar
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="reactivar({{ $usuario->id }})"
                                        wire:confirm="¿Reactivar a {{ $usuario->name }} {{ $usuario->apellido }} (DNI {{ $usuario->dni }})?"
                                        class="text-sm text-green-700 dark:text-green-400 underline"
                                    >
                                        Reactivar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                No se encontraron usuarios.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $usuarios->links() }}
    </div>
</div>
