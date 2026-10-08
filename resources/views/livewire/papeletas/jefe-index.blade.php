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

        {{-- Buscar por trabajador: filtra todas las listas de abajo. --}}
        <div class="max-w-xs">
            <label for="jefe-index-buscar" class="block text-sm font-medium mb-1 text-gray-700 dark:text-tinta-50/80">Buscar trabajador</label>
            <input id="jefe-index-buscar" type="text" wire:model.live.debounce.300ms="buscar" placeholder="Nombre, apellido o DNI..."
                   class="w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500">
        </div>

        {{-- Por decidir --}}
        <div class="glass-card overflow-hidden border-l-4 border-amber-500 dark:border-amber-400">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                    Por decidir
                    <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-300">{{ $porDecidir->total() }}</span>
                </h3>
            </div>
            @if ($porDecidir->isEmpty())
                <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">{{ $buscar ? 'Ningún trabajador coincide con la búsqueda.' : 'No tienes papeletas pendientes de decisión.' }}</p>
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
                @if ($porDecidir->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $porDecidir->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            @endif
        </div>

        {{-- Observadas por mí: esperando la respuesta del trabajador --}}
        @if ($observadasPorMi->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-gray-300 dark:border-white/15">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        Observadas por ti — esperan respuesta del trabajador
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-gray-500/15 text-gray-600 dark:text-tinta-100/70">{{ $observadasPorMi->total() }}</span>
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
                @if ($observadasPorMi->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $observadasPorMi->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Observaciones de RRHH que debo reconocer --}}
        @if ($observacionesRrhh->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-red-500 dark:border-red-400">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        Observadas por RRHH — requieren tu reconocimiento
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/15 text-red-700 dark:text-red-300">{{ $observacionesRrhh->total() }}</span>
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
                @if ($observacionesRrhh->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $observacionesRrhh->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Observaciones post-hoc de RRHH que debo responder (solo el jefe que autorizó) --}}
        @if ($posthocPorResponder->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-orange-500 dark:border-orange-400">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        RRHH observó tu autorización fuera de horario — requieren tu respuesta
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-orange-500/15 text-orange-700 dark:text-orange-300">{{ $posthocPorResponder->total() }}</span>
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
                            @foreach ($posthocPorResponder as $papeleta)
                                <tr wire:key="posthoc-responder-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('jefe.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Responder →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($posthocPorResponder->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $posthocPorResponder->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- En curso --}}
        @if ($enCurso->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-tinta-400 dark:border-tinta-400/40">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        En curso
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-tinta-500/15 text-tinta-700 dark:text-tinta-300">{{ $enCurso->total() }}</span>
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
                @if ($enCurso->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $enCurso->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Papeletas del turno: siguen visibles después de decidir, hasta que termina el turno --}}
        @if ($delTurno->isNotEmpty())
            <div class="glass-card overflow-hidden border-l-4 border-emerald-500 dark:border-emerald-400">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 flex items-center gap-2">
                        Papeletas del turno
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">{{ $delTurno->total() }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-tinta-100/50 mt-0.5">Se muestran hasta que finalice el turno.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead class="bg-tinta-50/70 dark:bg-white/5">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Trabajador</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Motivo</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Estado</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Fin de turno</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-transparent">
                            @foreach ($delTurno as $papeleta)
                                <tr wire:key="del-turno-{{ $papeleta->id }}" class="shadow-[0_0_0_1px_rgb(229,231,235)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.15)] hover:shadow-[0_0_0_1px_rgb(99,102,241)] dark:hover:shadow-[0_0_0_1px_rgb(129,140,248)] transition-shadow rounded-lg">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm"><x-estado-papeleta :estado="$papeleta->estado" :abandono="$papeleta->esAbandono()" /></td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $papeleta->fin_turno_at?->format('d/m H:i') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('jefe.papeletas.show', $papeleta) }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Ver</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($delTurno->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        {{ $delTurno->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
