<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
            {{ $esJefeDeArea ? 'Usuarios de mi área' : 'Mis trabajadores' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm text-gray-500 dark:text-tinta-100/60">
                    @if ($esJefeDeArea)
                        Todos los usuarios de tu área y sus sub-unidades.
                    @else
                        Solo ves a los trabajadores de los que eres jefe inmediato (automático o adicional).
                    @endif
                </p>

                <div class="flex items-center gap-2">
                    @if ($esJefeDeArea)
                        <a href="{{ route('unidad-organica.oficinas.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-white dark:bg-white/5 border border-tinta-600 text-tinta-700 dark:text-tinta-200 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-tinta-50">
                            Nueva oficina
                        </a>
                    @endif

                    @if ($puedeCrear)
                        <a href="{{ route('usuarios.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-tinta-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-tinta-700">
                            Crear usuario
                        </a>
                    @endif
                </div>
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
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-transparent divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($usuarios as $usuario)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $usuario->nombre_completo }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $usuario->dni }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $usuario->regimen }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-tinta-100/60">{{ $usuario->unidadOrganica?->nombre ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if ($puedeCrear)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-2">¿Vas a asignarte como jefe inmediato de alguien que ya existe en el sistema?</h3>
                    <p class="text-sm text-gray-500 dark:text-tinta-100/60 mb-4">Búscalo por DNI. Si ya tiene jefe(s) asignado(s), te los mostraremos antes de confirmar.</p>
                    <form method="POST" action="{{ route('usuarios.buscar') }}" class="flex items-end gap-3">
                        @csrf
                        <div>
                            <x-label for="dni" value="DNI" />
                            <x-input id="dni" name="dni" type="text" maxlength="8" required class="mt-1 block" />
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Buscar
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
