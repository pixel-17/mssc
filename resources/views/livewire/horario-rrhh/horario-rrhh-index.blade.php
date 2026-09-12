<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            Horario de RRHH
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
                        <th class="px-4 py-3">Día</th>
                        <th class="px-4 py-3">Inicio</th>
                        <th class="px-4 py-3">Fin</th>
                        <th class="px-4 py-3">Activo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($horarios as $horario)
                        <tr wire:key="horario-{{ $horario->id }}">
                            <td class="px-4 py-3 font-semibold">{{ \App\Livewire\HorarioRrhh\HorarioRrhhIndex::DIAS[$horario->dia_semana] ?? $horario->dia_semana }}</td>
                            <td class="px-4 py-3">{{ $horario->hora_inicio }}</td>
                            <td class="px-4 py-3">{{ $horario->hora_fin }}</td>
                            <td class="px-4 py-3">{{ $horario->activo ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('horario-rrhh.editar', $horario) }}" class="text-sm text-ocean-700 dark:text-ocean-300 underline">
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
