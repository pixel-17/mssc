@php
    $puedeCancelar = $papeleta->trabajador_id === auth()->id() && $papeleta->estado->equals(\App\States\Papeleta\PendienteJefe::class);
    $puedeMarcarRetorno = $papeleta->trabajador_id === auth()->id() && $papeleta->estado->equals(\App\States\Papeleta\AutorizadaYCorriendo::class) && ! $papeleta->retorno;
    $sustentoPendiente = $papeleta->sustentos->firstWhere('estado', 'pendiente');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Papeleta #{{ $papeleta->id }} · {{ $papeleta->motivo->nombre }}
            </h2>
            <x-estado-papeleta :estado="$papeleta->estado" class="text-sm" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            @include('papeletas._info', ['papeleta' => $papeleta])

            @if ($puedeCancelar)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <form method="POST" action="{{ route('trabajador.papeletas.cancelar', $papeleta) }}"
                          onsubmit="return confirm('¿Cancelar esta papeleta?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">
                            Cancelar papeleta
                        </button>
                    </form>
                </div>
            @endif

            @if ($puedeMarcarRetorno)
                <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ lat: '', lng: '', obteniendo: false, error: '' }">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Marcar retorno</h3>
                    <form method="POST" action="{{ route('trabajador.papeletas.retorno.store', $papeleta) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Foto</label>
                            <input type="file" name="foto" accept="image/*" required
                                   class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>

                        <div>
                            <button type="button"
                                    @click="obteniendo = true; error = ''; navigator.geolocation.getCurrentPosition(
                                        (p) => { lat = p.coords.latitude; lng = p.coords.longitude; obteniendo = false; },
                                        (e) => { error = 'No se pudo obtener tu ubicación: ' + e.message; obteniendo = false; }
                                    )"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50">
                                <span x-show="!obteniendo">📍 Usar mi ubicación actual</span>
                                <span x-show="obteniendo">Obteniendo ubicación...</span>
                            </button>
                            <p class="mt-1 text-xs text-red-600" x-show="error" x-text="error"></p>
                            <p class="mt-1 text-xs text-green-700" x-show="lat && lng">Ubicación capturada: <span x-text="lat"></span>, <span x-text="lng"></span></p>
                        </div>

                        <input type="hidden" name="latitud" :value="lat">
                        <input type="hidden" name="longitud" :value="lng">

                        <button type="submit" :disabled="!lat || !lng"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            Confirmar retorno
                        </button>
                    </form>
                </div>
            @endif

            @if ($sustentoPendiente)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Sustento pendiente</h3>
                    <p class="text-xs text-gray-500 mb-3">
                        Fecha límite: {{ $sustentoPendiente->fecha_limite?->format('d/m/Y H:i') }}
                    </p>
                    <form method="POST" action="{{ route('trabajador.papeletas.sustento.store', $sustentoPendiente) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <input type="file" name="archivo" required
                               class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Presentar sustento
                        </button>
                    </form>
                </div>
            @endif

            @include('papeletas._historial', ['papeleta' => $papeleta])
        </div>
    </div>
</x-app-layout>
