<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Unidades orgánicas
            </h2>

            <a href="{{ route('unidades-organicas.crear') }}" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                + Nueva unidad
            </a>
        </div>

        @if (session('mensaje'))
            <div class="glass-card p-4 text-sm text-green-700 dark:text-green-400">
                {{ session('mensaje') }}
            </div>
        @endif

        <div class="glass-card overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Unidad padre</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Jefe</th>
                        <th class="px-4 py-3">Activo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($unidades as $unidad)
                        <tr wire:key="unidad-{{ $unidad->id }}">
                            <td class="px-4 py-3">{{ $unidad->nombre }}</td>
                            <td class="px-4 py-3">{{ $unidad->padre?->nombre ?? '— (raíz)' }}</td>
                            <td class="px-4 py-3">{{ $unidad->tipo }}</td>
                            <td class="px-4 py-3">{{ $unidad->jefe?->name }}</td>
                            <td class="px-4 py-3">{{ $unidad->activo ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('unidades-organicas.editar', $unidad) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Editar
                                </a>
                                <button
                                    type="button"
                                    wire:click="eliminar({{ $unidad->id }})"
                                    wire:confirm="¿Eliminar esta unidad orgánica?"
                                    class="text-sm text-red-600 dark:text-red-400 underline"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Aún no hay unidades orgánicas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
