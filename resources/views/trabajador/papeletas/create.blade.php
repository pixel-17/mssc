<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            Nueva papeleta
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="glass-card p-6">
                <form method="POST" action="{{ route('trabajador.papeletas.store') }}" enctype="multipart/form-data" class="space-y-6"
                      x-data="{ motivoId: '{{ old('motivo_id') }}', motivos: {{ $motivos->toJson() }} }">
                    @csrf

                    <div>
                        <label for="motivo_id" class="block text-sm font-medium text-gray-700">Motivo</label>
                        <select id="motivo_id" name="motivo_id" x-model="motivoId" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-ocean-500 focus:ring-ocean-500 sm:text-sm">
                            <option value="">Selecciona un motivo</option>
                            @foreach ($motivos as $motivo)
                                <option value="{{ $motivo->id }}">{{ $motivo->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500" x-show="motivoId">
                            <template x-for="m in motivos.filter(m => m.id == motivoId)" :key="m.id">
                                <span>
                                    <span x-show="m.permite_bypass_aprobacion">⚡ Salida inmediata, sin aprobación previa (requiere justificación).</span>
                                    <span x-show="m.adjunto === 'obligatorio'">📎 Este motivo exige adjuntar sustento.</span>
                                    <span x-show="m.requiere_sustento_en_retorno">🩺 Al retornar deberás presentar sustento dentro del plazo configurado.</span>
                                </span>
                            </template>
                        </p>
                    </div>

                    <div>
                        <label for="justificacion" class="block text-sm font-medium text-gray-700">Justificación</label>
                        <textarea id="justificacion" name="justificacion" rows="3" maxlength="2000"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-ocean-500 focus:ring-ocean-500 sm:text-sm">{{ old('justificacion') }}</textarea>
                    </div>

                    <div>
                        <label for="adjunto_inicial_path" class="block text-sm font-medium text-gray-700">Adjunto (opcional según motivo)</label>
                        <input type="file" id="adjunto_inicial_path" name="adjunto_inicial_path"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-ocean-50 file:text-ocean-700 hover:file:bg-ocean-100">
                        <p class="mt-1 text-xs text-gray-400">Máximo 10 MB.</p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('trabajador.papeletas.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-ocean-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-ocean-700 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 transition">
                            Crear papeleta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
