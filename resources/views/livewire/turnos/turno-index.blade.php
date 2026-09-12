<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Turnos
            </h2>

            <a href="{{ route('turnos.crear') }}" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                + Nuevo turno
            </a>
        </div>

        @if (session('mensaje'))
            <div class="glass-card p-4 text-sm text-green-700 dark:text-green-400">
                {{ session('mensaje') }}
            </div>
        @endif

        <div class="max-w-xs">
            <label class="block text-sm font-medium mb-1">Filtrar por trabajador</label>
            <select wire:model.live="userId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                <option value="">Todos</option>
                @foreach ($trabajadores as $id => $nombre)
                    <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="glass-card overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Sede</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Inicio</th>
                        <th class="px-4 py-3">Fin</th>
                        <th class="px-4 py-3">Descanso</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($turnos as $turno)
                        <tr wire:key="turno-{{ $turno->id }}">
                            <td class="px-4 py-3">{{ $turno->usuario?->name }}</td>
                            <td class="px-4 py-3">{{ $turno->sede?->nombre }}</td>
                            <td class="px-4 py-3">{{ $turno->fecha?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $turno->hora_inicio }}</td>
                            <td class="px-4 py-3">{{ $turno->hora_fin }}</td>
                            <td class="px-4 py-3">{{ $turno->es_descanso ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('turnos.editar', $turno) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Editar
                                </a>
                                <button
                                    type="button"
                                    wire:click="eliminar({{ $turno->id }})"
                                    wire:confirm="¿Eliminar este turno?"
                                    class="text-sm text-red-600 dark:text-red-400 underline"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Aún no hay turnos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $turnos->links() }}
    </div>
</div>
