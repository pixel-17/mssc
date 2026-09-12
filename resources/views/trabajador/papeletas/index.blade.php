<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Mis papeletas
            </h2>
            <a href="{{ route('trabajador.papeletas.create') }}"
               class="inline-flex items-center px-4 py-2 bg-ocean-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-ocean-700 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 transition">
                + Nueva papeleta
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="glass-card overflow-hidden">
                @if ($papeletas->isEmpty())
                    <div class="p-6 text-sm text-gray-500">
                        Todavía no tienes papeletas registradas.
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-ocean-50/70">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Motivo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sede</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Día operativo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($papeletas as $papeleta)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $papeleta->motivo->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->sede->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->dia_operativo?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm"><x-estado-papeleta :estado="$papeleta->estado" /></td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <a href="{{ route('trabajador.papeletas.show', $papeleta) }}" class="text-ocean-600 hover:text-ocean-900 font-medium">Ver</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $papeletas->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
