<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Feriados
            </h2>

            <a href="{{ route('feriados.crear') }}" class="inline-flex items-center px-4 py-2 bg-ocean-800 text-white rounded-md text-sm">
                + Nuevo feriado
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
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Descripción</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($feriados as $feriado)
                        <tr wire:key="feriado-{{ $feriado->id }}">
                            <td class="px-4 py-3">{{ $feriado->fecha?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $feriado->descripcion }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('feriados.editar', $feriado) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Editar
                                </a>
                                <button
                                    type="button"
                                    wire:click="eliminar({{ $feriado->id }})"
                                    wire:confirm="¿Eliminar este feriado?"
                                    class="text-sm text-red-600 dark:text-red-400 underline"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Aún no hay feriados registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
