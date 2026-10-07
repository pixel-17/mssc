<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">Decisiones de RR. HH.</h2>
            <p class="text-sm text-gray-500 dark:text-tinta-50/70">Todo lo que RR. HH. resolvió sobre las papeletas: quién, cuándo y con qué justificación.</p>
        </div>

        <div class="glass-card p-4 flex flex-wrap gap-4 items-end">
            <div>
                <label for="decisiones-mes" class="block text-sm font-medium mb-1">Mes</label>
                <input id="decisiones-mes" type="month" wire:model.live="mes" class="rounded-md border-gray-300 dark:bg-gray-800">
            </div>
            <div>
                <label for="decisiones-actor" class="block text-sm font-medium mb-1">Quién decidió</label>
                <select id="decisiones-actor" wire:model.live="actorId" class="rounded-md border-gray-300 dark:bg-gray-800">
                    <option value="">Todos</option>
                    @foreach ($actores as $actor)
                        <option value="{{ $actor->id }}">{{ trim($actor->name.' '.$actor->apellido) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="glass-card overflow-hidden">
            @if ($eventos->isEmpty())
                <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">No hay decisiones de RR. HH. en este periodo.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                @foreach (['Fecha', 'Decidió', 'Papeleta', 'Resultado', 'Justificación'] as $columna)
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">{{ $columna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($eventos as $evento)
                                @php
                                    [$etiqueta] = \App\Support\PapeletaEstadoPresentacion::para('App\\States\\Papeleta\\'.$evento->estado_nuevo);
                                @endphp
                                <tr wire:key="decision-{{ $evento->id }}" class="border-t border-gray-100 dark:border-white/10 align-top">
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $evento->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $evento->actor?->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('rrhh.papeletas.show', $evento->papeleta_id) }}" class="text-tinta-600 dark:text-tinta-300 underline">
                                            #{{ $evento->papeleta_id }} · {{ $evento->papeleta?->trabajador?->nombre_completo }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">
                                        {{ $etiqueta }}
                                        @if (! empty($evento->metadata['correccion_rrhh']))
                                            <span class="block text-xs">(corrección de datos)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $evento->justificacion ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($eventos->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">{{ $eventos->links(data: ['scrollTo' => false]) }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
