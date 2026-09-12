<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            Configuraciones
        </h2>

        @if (session('mensaje'))
            <div class="glass-card p-4 text-sm text-green-700 dark:text-green-400">
                {{ session('mensaje') }}
            </div>
        @endif

        <div class="glass-card overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Clave</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Descripción</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($configuraciones as $configuracion)
                        <tr wire:key="config-{{ $configuracion->id }}">
                            <td class="px-4 py-3 font-semibold">{{ $configuracion->clave }}</td>
                            <td class="px-4 py-3">{{ $configuracion->valor }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $configuracion->descripcion }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('configuraciones.editar', $configuracion) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
