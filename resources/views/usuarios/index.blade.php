<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            {{ $esJefeDeArea ? 'Usuarios de mi área' : 'Mis trabajadores' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm text-gray-500">
                    @if ($esJefeDeArea)
                        Todos los usuarios de tu área y sus sub-unidades.
                    @else
                        Solo ves a los trabajadores de los que eres jefe inmediato (automático o adicional).
                    @endif
                </p>

                @if ($puedeCrear)
                    <a href="{{ route('usuarios.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-ocean-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-ocean-700">
                        Crear usuario
                    </a>
                @endif
            </div>

            <div class="glass-card overflow-hidden">
                @if ($usuarios->isEmpty())
                    <p class="p-4 text-sm text-gray-500">Todavía no tienes usuarios a cargo.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-ocean-50/70">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Régimen</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unidad</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($usuarios as $usuario)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $usuario->nombre_completo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $usuario->dni }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $usuario->regimen }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $usuario->unidadOrganica?->nombre ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($puedeCrear)
                <div class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">¿Vas a asignarte como jefe inmediato de alguien que ya existe en el sistema?</h3>
                    <p class="text-sm text-gray-500 mb-4">Búscalo por DNI. Si ya tiene jefe(s) asignado(s), te los mostraremos antes de confirmar.</p>
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
