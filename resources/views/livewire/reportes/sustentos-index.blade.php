<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">

        <div class="print:hidden">
            <h2 class="font-bold text-2xl text-ocean-950 dark:text-white leading-tight tracking-tight">
                Sustentos
            </h2>
            <p class="text-sm text-gray-500 dark:text-ocean-50/70">
                Archivos que los trabajadores suben para justificar su retorno (motivo Salud) y su estado de revisión.
            </p>
        </div>

        {{-- ============================= FILTROS ============================= --}}
        <div class="glass-card p-4 print:hidden">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Buscar por nombre o DNI</label>
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="buscar"
                        placeholder="Nombre, apellido o DNI..."
                        class="w-full rounded-md border-gray-300 dark:bg-gray-800"
                    >
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Trabajador</label>
                    <select wire:model.live="trabajadorId" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">Todos</option>
                        @foreach ($trabajadoresDisponibles as $trabajador)
                            <option value="{{ $trabajador->id }}">{{ trim($trabajador->name.' '.$trabajador->apellido) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="max-w-xs">
                    <label class="block text-sm font-medium mb-1">Estado</label>
                    <select wire:model.live="estado" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendiente de subir</option>
                        <option value="presentado">Presentado (por revisar)</option>
                        <option value="aprobado">Aprobado</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Desde</label>
                    <input type="date" wire:model.live="desde" class="rounded-md border-gray-300 dark:bg-gray-800">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Hasta</label>
                    <input type="date" wire:model.live="hasta" class="rounded-md border-gray-300 dark:bg-gray-800">
                </div>

                <button type="button" wire:click="limpiarFiltros" class="text-xs text-ocean-600 hover:text-ocean-900 underline pb-2">
                    Limpiar filtros
                </button>

                <button type="button" onclick="window.print()" class="btn-secondary text-xs ml-auto">
                    Imprimir
                </button>
            </div>
        </div>

        {{-- ============================= TABLA ============================= --}}
        <div class="glass-card overflow-x-auto">
            @if ($sustentos->isEmpty())
                <p class="p-4 text-sm text-gray-500">No hay sustentos con estos filtros.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-ocean-50/70 dark:bg-gray-800">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                            <th class="px-4 py-2">Trabajador</th>
                            <th class="px-4 py-2">Sede</th>
                            <th class="px-4 py-2">Papeleta</th>
                            <th class="px-4 py-2">Fecha límite</th>
                            <th class="px-4 py-2">Presentado</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2">Revisado por</th>
                            <th class="px-4 py-2 print:hidden">Archivo</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-transparent divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($sustentos as $sustento)
                            <tr wire:key="sustento-{{ $sustento->id }}">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ $sustento->papeleta->trabajador->nombre_completo }}
                                    <span class="block text-xs text-gray-400">DNI {{ $sustento->papeleta->trabajador->dni ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $sustento->papeleta->trabajador->sede?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $sustento->papeleta->dia_operativo->format('d/m/Y') }} · {{ $sustento->papeleta->motivo->nombre }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $sustento->fecha_limite?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $sustento->presentado_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @php [$etiquetaSustento, $clasesSustento] = \App\Support\SustentoEstadoPresentacion::para($sustento->estado); @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ring-1 ring-inset {{ $clasesSustento }}">
                                        {{ $etiquetaSustento }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $sustento->revisadoPor?->nombre_completo ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm print:hidden">
                                    @if ($sustento->archivo_path)
                                        <a href="{{ route('sustentos.archivo', $sustento) }}" target="_blank" class="text-ocean-600 hover:text-ocean-900 underline">Ver</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-4 print:hidden">
                    {{ $sustentos->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
