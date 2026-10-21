<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
            {{ $esJefeDeArea ? 'Usuarios de mi área' : 'Mis trabajadores' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (! empty($avisosJefatura ?? []))
                <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4" role="status">
                    <p class="text-sm font-semibold text-yellow-800">Unidades sin jefe</p>
                    <ul class="mt-1 list-disc ps-5 text-sm text-yellow-800">
                        @foreach ($avisosJefatura as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-yellow-700">Hasta que se asigne, quienes dependen directamente de esa jefatura no pueden crear papeletas.</p>
                </div>
            @endif

            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm text-gray-500 dark:text-tinta-100/60">
                    @if ($esJefeDeArea)
                        Todos los usuarios de tu área y sus sub-unidades.
                    @else
                        Solo ves a los trabajadores de los que eres jefe inmediato.
                    @endif
                </p>

                @if ($puedeCrear)
                    <a href="{{ route('usuarios.crear') }}"
                       class="inline-flex items-center px-4 py-2 bg-tinta-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-tinta-700">
                        Crear usuario
                    </a>
                @endif
            </div>

            <div class="glass-card overflow-hidden">
                @if ($usuarios->isEmpty())
                    <p class="p-4 text-sm text-gray-500 dark:text-tinta-100/50">Todavía no tienes usuarios a cargo.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                            <thead class="bg-tinta-50/70 dark:bg-white/5">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Nombre</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">DNI</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Régimen</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Unidad</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-tinta-100/50 uppercase">Estado</th>
                                    <th scope="col" class="px-4 py-2"></th>
                                    <th scope="col" class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-transparent divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($usuarios as $usuario)
                                    <tr @class(['opacity-60 bg-gray-50 dark:bg-white/[0.03]' => ! $usuario->activo])>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $usuario->nombre_completo }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $usuario->dni }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $usuario->regimen }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $usuario->unidadOrganica?->nombre ?? '—' }}</td>
                                        <td class="px-4 py-3"><x-admin.estado :activo="$usuario->activo" etiquetaNo="Desactivado" /></td>
                                        <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                            <a href="{{ route('reportes.trabajador-historial', ['trabajadorId' => $usuario->id]) }}"
                                               title="Ver su historial de papeletas"
                                               class="inline-flex items-center gap-1 rounded-md border border-tinta-200 px-2.5 py-1 text-xs font-semibold text-tinta-700 hover:bg-tinta-50 dark:border-white/10 dark:text-tinta-300 dark:hover:bg-white/5">
                                                Ficha
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                            @can('editar', $usuario)
                                                <a href="{{ route('usuarios.editar', $usuario) }}"
                                                   class="inline-flex items-center gap-1 rounded-md bg-tinta-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-tinta-700">
                                                    Editar
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>