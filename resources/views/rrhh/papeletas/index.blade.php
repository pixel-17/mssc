<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            Bandeja de RRHH
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">
            {{-- Por decidir --}}
            <div class="glass-card overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Por decidir ({{ $porDecidir->count() }})</h3>
                </div>
                @if ($porDecidir->isEmpty())
                    <p class="p-4 text-sm text-gray-500">No hay papeletas pendientes de RRHH.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-ocean-50/70 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trabajador</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Creada</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-transparent dark:divide-white/10">
                            @foreach ($porDecidir as $papeleta)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2 flex-wrap">
                                            <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-ocean-600 hover:text-ocean-900 font-medium mr-2">Ver</a>
                                            <form method="POST" action="{{ route('rrhh.papeletas.aprobar', $papeleta) }}" x-data="{{ enviando: false }}" @submit="enviando = true">
                                                @csrf
                                                <button type="submit" :disabled="enviando" :class="{{ 'opacity-50 cursor-not-allowed': enviando }}" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-semibold rounded-md text-white bg-green-600 hover:bg-green-700">
                                                    <span x-show="! enviando">Aprobar</span>
                                                    <span x-show="enviando" x-cloak>Aprobando…</span>
                                                </button>
                                            </form>
                                            <x-accion-comentario :action="route('rrhh.papeletas.observar', $papeleta)" label="Observar" color="orange" />
                                            <x-accion-comentario :action="route('rrhh.papeletas.rechazar', $papeleta)" label="Rechazar" color="red" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Revisión post-hoc --}}
            @if ($posthocPendientes->isNotEmpty())
                <div class="glass-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Revisión post-hoc — jefe autorizó fuera de horario RRHH ({{ $posthocPendientes->count() }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-ocean-50/70 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trabajador</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Autorizó</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-transparent dark:divide-white/10">
                            @foreach ($posthocPendientes as $papeleta)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->resueltoPorJefe?->nombre_completo ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-ocean-600 hover:text-ocean-900 font-medium">Revisar →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Sustentos por revisar --}}
            @if ($sustentosPorRevisar->isNotEmpty())
                <div class="glass-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Sustentos por revisar ({{ $sustentosPorRevisar->count() }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-ocean-50/70 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trabajador</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-transparent dark:divide-white/10">
                            @foreach ($sustentosPorRevisar as $papeleta)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $papeleta->trabajador->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('rrhh.papeletas.show', $papeleta) }}" class="text-xs text-ocean-600 hover:text-ocean-900 font-medium">Revisar sustento →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
