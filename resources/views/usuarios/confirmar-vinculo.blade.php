<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Confirmar vínculo
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div>
                    <p class="text-sm text-gray-500">Trabajador encontrado</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $trabajador->nombre_completo }}</p>
                    <p class="text-sm text-gray-500">DNI {{ $trabajador->dni }} · Régimen {{ $trabajador->regimen }}</p>
                </div>

                @if (! empty($jefesActuales))
                    <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4">
                        <p class="text-sm font-medium text-yellow-800">Este trabajador ya tiene jefe(s) inmediato(s):</p>
                        <ul class="mt-2 text-sm text-yellow-800 list-disc list-inside">
                            @foreach ($jefesActuales as $nombre)
                                <li>{{ $nombre }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-sm text-yellow-800">Puedes añadirte como jefe inmediato adicional. Ambos podrán decidir sobre sus papeletas.</p>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Este trabajador todavía no tiene jefe inmediato asignado.</p>
                @endif

                <form method="POST" action="{{ route('usuarios.vincular', $trabajador) }}" class="space-y-4">
                    @csrf

                    @if (! empty($jefesActuales))
                        <label class="flex items-start gap-2">
                            <x-checkbox name="confirmado" value="1" required class="mt-1" />
                            <span class="text-sm text-gray-700">Confirmo que quiero agregarme como jefe inmediato adicional, aunque ya tenga otro(s) jefe(s).</span>
                        </label>
                    @else
                        <input type="hidden" name="confirmado" value="1">
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Confirmar vínculo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
