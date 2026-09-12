<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Sedes
            </h2>

            <a href="{{ route('sedes.crear') }}" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                + Nueva sede
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
                        <th class="px-4 py-3">Dirección</th>
                        <th class="px-4 py-3">Radio (m)</th>
                        <th class="px-4 py-3">Trabajadores</th>
                        <th class="px-4 py-3">Activa</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($sedes as $sede)
                        <tr wire:key="sede-{{ $sede->id }}">
                            <td class="px-4 py-3">{{ $sede->nombre }}</td>
                            <td class="px-4 py-3">{{ $sede->direccion }}</td>
                            <td class="px-4 py-3">{{ $sede->radio_metros }}</td>
                            <td class="px-4 py-3">{{ $sede->usuarios_count }}</td>
                            <td class="px-4 py-3">{{ $sede->activo ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('sedes.editar', $sede) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Editar
                                </a>
                                <button
                                    type="button"
                                    wire:click="eliminar({{ $sede->id }})"
                                    wire:confirm="¿Eliminar esta sede?"
                                    class="text-sm text-red-600 dark:text-red-400 underline"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Aún no hay sedes registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
