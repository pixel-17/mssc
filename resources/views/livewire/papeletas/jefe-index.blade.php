<div>
    <div class="max-w-6xl mx-auto py-12 sm:px-6 lg:px-8 space-y-8">
        @php
            // Mismo tope configurable que usa ObservarJefeAction (Configuraciones >
            // TOPE_OBSERVACIONES). Se calcula una vez para toda la bandeja.
            $topeObservaciones = (int) \App\Models\Configuracion::valorDe('TOPE_OBSERVACIONES', 3);
        @endphp
        <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
            Bandeja de Jefe
        </h2>

        <x-flash-messages />

        {{-- Por decidir --}}
        <div class="glass-card overflow-hidden border-l-4 border-amber-500 dark:border-amber-400">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                    Por decidir
                    <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-300">{{ $porDecidir->count() }}</span>
                </h3>
            </div>
            @if ($porDecidir->isEmpty())
                <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">No tienes papeletas pendientes de decisión.</p>
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
                                        <div class="flex items-center justify-end gap-2 flex-nowrap">
                                            <a href="{{ route('jefe.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium mr-2">Ver</a>
                                            <x-boton-confirmar
                                                :action="route('jefe.papeletas.aprobar', $papeleta)"
                                                label="Aprobar"
                                                color="green"
                                                confirmText="¿Aprobar la papeleta de {{ $papeleta->trabajador->nombre_completo }}?"
                                            />
                                            <x-accion-comentario :action="route('jefe.papeletas.observar', $papeleta)" label="Observar" color="orange" opcion="requiere_adjunto" opcionLabel="Además de responder por escrito, debe adjuntar un archivo" :opcionMarcada="false" :aviso="$papeleta->contador_observaciones_jefe >= $topeObservaciones - 1 ? \"Esta papeleta ya tiene {$papeleta->contador_observaciones_jefe}/{$topeObservaciones} observaciones. Si la observas, alcanzará el tope y el sistema la rechazará automáticamente en vez de esperar respuesta del trabajador.\" : null" />
                                            <x-accion-comentario :action="route('jefe.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Observadas por mí: esperando la respuesta del trabajador --}}
        @if ($observadasPorMi->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-gray-300 dark:border-white/15">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        Observadas por ti — esperan respuesta del trabajador
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-gray-500/15 text-gray-600 dark:text-tinta-100/70">{{ $observadasPorMi->count() }}</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Se espera</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($observadasPorMi as $papeleta)
                                <tr wire:key="observada-jefe-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">
                                        {{ $papeleta->observacion_requiere_adjunto ? 'Respuesta escrita + archivo adjunto' : 'Respuesta escrita' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2 flex-nowrap">
                                            <a href="{{ route('jefe.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium mr-2">Ver</a>
                                            <x-accion-comentario :action="route('jefe.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Observaciones de RRHH que debo reconocer --}}
        @if ($observacionesRrhh->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-red-500 dark:border-red-400">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        Observadas por RRHH — requieren tu reconocimiento
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/15 text-red-700 dark:text-red-300">{{ $observacionesRrhh->count() }}</span>
                    </h3>
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
                            @foreach ($observacionesRrhh as $papeleta)
                                <tr wire:key="observada-rrhh-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('jefe.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium mr-2">Ver</a>
                                            <x-accion-comentario :action="route('jefe.papeletas.reconocer-observacion-rrhh', $papeleta)" label="Reconocer" color="orange" placeholder="Comenta lo que corresponda antes de reabrir la papeleta..." />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- En curso --}}
        @if ($enCurso->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-tinta-400 dark:border-tinta-400/40">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        En curso
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-tinta-500/15 text-tinta-700 dark:text-tinta-300">{{ $enCurso->count() }}</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Salida</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($enCurso as $papeleta)
                                <tr wire:key="en-curso-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->hora_salida_real?->format('d/m H:i') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('jefe.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Gestionar retorno →</a>
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
            <div class="glass-card overflow-hidden border-l-4 border-purple-500 dark:border-purple-400">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        Sustentos por revisar
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/15 text-purple-700 dark:text-purple-300">{{ $sustentosPorRevisar->count() }}</span>
                    </h3>
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
                                        <a href="{{ route('jefe.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Revisar sustento →</a>
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
