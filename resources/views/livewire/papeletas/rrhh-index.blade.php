<div>
    <div class="max-w-6xl mx-auto py-12 sm:px-6 lg:px-8 space-y-8">
        <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
            Bandeja de RRHH
        </h2>

        <x-flash-messages />

        {{-- Buscar por trabajador: filtra las tres listas de abajo. --}}
        <div class="max-w-xs">
            <label for="rrhh-index-buscar" class="block text-sm font-medium mb-1 text-gray-700 dark:text-tinta-50/80">Buscar trabajador</label>
            <input id="rrhh-index-buscar" type="text" wire:model.live.debounce.300ms="buscar" placeholder="Nombre o apellido..."
                   class="w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500">
        </div>

        {{-- Por decidir --}}
        <div class="glass-card overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Por decidir ({{ $porDecidir->count() }})</h3>
            </div>
            @if ($porDecidir->isEmpty())
                <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">
                    {{ $buscar ? 'Ningún trabajador coincide con la búsqueda.' : 'No hay papeletas pendientes de RRHH.' }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Creada</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($porDecidir as $papeleta)
                                <tr wire:key="por-decidir-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2 flex-wrap">
                                            <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium mr-2">Ver</a>
                                            <x-boton-confirmar
                                                :action="route('rrhh.papeletas.aprobar', $papeleta)"
                                                label="Aprobar"
                                                color="green"
                                                confirmText="¿Aprobar la papeleta de {{ $papeleta->trabajador->nombre_completo }}?"
                                            />
                                            @unless ($papeleta->sinJefatura())
                                                <x-accion-comentario :action="route('rrhh.papeletas.observar', $papeleta)" label="Observar" color="orange" />
                                            @endunless
                                            <x-accion-comentario :action="route('rrhh.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Revisión post-hoc --}}
        @if ($posthocPendientes->isNotEmpty())
            <div class="glass-card overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Revisión post-hoc — jefe autorizó fuera de horario RRHH ({{ $posthocPendientes->count() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Autorizó</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($posthocPendientes as $papeleta)
                                <tr wire:key="posthoc-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->resueltoPorJefe?->nombre_completo ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Revisar →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Sustentos por revisar --}}
        @if ($sustentosPorRevisar->isNotEmpty())
            <div class="glass-card overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Sustentos por revisar ({{ $sustentosPorRevisar->count() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($sustentosPorRevisar as $papeleta)
                                <tr wire:key="sustento-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Revisar sustento →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
