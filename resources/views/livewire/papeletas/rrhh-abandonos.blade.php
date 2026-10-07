<div>
    <div class="max-w-6xl mx-auto py-12 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">Abandonos</h2>
            <p class="text-sm text-gray-500 dark:text-tinta-50/70">
                Papeletas en las que el trabajador salió y no regresó, con quién las marcó y por qué.
            </p>
        </div>

        <div class="max-w-xs">
            <label for="abandonos-buscar" class="block text-sm font-medium mb-1 text-gray-700 dark:text-tinta-50/80">Buscar trabajador</label>
            <input id="abandonos-buscar" type="text" wire:model.live.debounce.300ms="buscar" placeholder="Nombre o apellido..."
                   class="w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500">
        </div>

        <div class="glass-card overflow-hidden">
            @if ($abandonos->isEmpty())
                <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">No hay abandonos registrados.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                @foreach (['Día', 'Trabajador', 'Motivo', 'Marcado por', 'Cuándo', 'Justificación', ''] as $columna)
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">{{ $columna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($abandonos as $papeleta)
                                @php $evento = $papeleta->historial->sortByDesc('id')->first(); @endphp
                                <tr wire:key="abandono-{{ $papeleta->id }}" class="border-t border-gray-100 dark:border-white/10 align-top">
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->dia_operativo->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">
                                        {{ $evento?->actor?->nombre_completo ?? 'Sistema (automático)' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $evento?->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $evento?->justificacion ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 font-medium">Ver →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($abandonos->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">{{ $abandonos->links(data: ['scrollTo' => false]) }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
