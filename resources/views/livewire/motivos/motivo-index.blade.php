<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Motivos
            </h2>

            <a href="{{ route('motivos.crear') }}" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                + Nuevo motivo
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
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Adjunto</th>
                        <th class="px-4 py-3">Bypass</th>
                        <th class="px-4 py-3">Sustento</th>
                        <th class="px-4 py-3">Cierre s/retorno</th>
                        <th class="px-4 py-3">Activo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($motivos as $motivo)
                        <tr wire:key="motivo-{{ $motivo->id }}">
                            <td class="px-4 py-3">{{ $motivo->codigo }}</td>
                            <td class="px-4 py-3">{{ $motivo->nombre }}</td>
                            <td class="px-4 py-3">{{ $motivo->adjunto }}</td>
                            <td class="px-4 py-3">{{ $motivo->permite_bypass_aprobacion ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $motivo->requiere_sustento_en_retorno ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $motivo->permite_cierre_sin_retorno ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $motivo->activo ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('motivos.editar', $motivo) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Editar
                                </a>
                                <button
                                    type="button"
                                    wire:click="eliminar({{ $motivo->id }})"
                                    wire:confirm="¿Eliminar este motivo?"
                                    class="text-sm text-red-600 dark:text-red-400 underline"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Aún no hay motivos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
