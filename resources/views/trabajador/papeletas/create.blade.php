<x-trabajador-layout titulo="Nueva papeleta" :volver-a="route('trabajador.papeletas.index')">
    <div x-data="{ motivoId: '{{ old('motivo_id') }}', motivos: {{ $motivos->toJson() }}, archivo: null }">
        <x-flash-messages />

        <form method="POST" action="{{ route('trabajador.papeletas.store') }}" enctype="multipart/form-data" class="space-y-5 mt-4">
            @csrf

            <div class="glass-card p-4 space-y-1.5">
                <x-label for="motivo_id" value="Motivo" />
                <select
                    id="motivo_id"
                    name="motivo_id"
                    x-model="motivoId"
                    required
                    class="w-full rounded-xl border-ocean-200 dark:border-white/15 bg-white/70 dark:bg-white/10 dark:text-white backdrop-blur focus:border-ocean-500 focus:ring-ocean-500 shadow-sm"
                >
                    <option value="">Selecciona un motivo</option>
                    @foreach ($motivos as $motivo)
                        <option value="{{ $motivo->id }}">{{ $motivo->nombre }}</option>
                    @endforeach
                </select>

                <template x-if="motivoId">
                    <template x-for="m in motivos.filter(m => m.id == motivoId)" :key="m.id">
                        <ul class="text-xs text-gray-500 dark:text-ocean-100/60 space-y-1 pt-2">
                            <li x-show="m.permite_bypass_aprobacion">⚡ Salida inmediata, sin aprobación previa (requiere justificación).</li>
                            <li x-show="m.adjunto === 'obligatorio'">📎 Este motivo exige adjuntar sustento.</li>
                            <li x-show="m.requiere_sustento_en_retorno">🩺 Al retornar deberás presentar sustento dentro del plazo configurado.</li>
                        </ul>
                    </template>
                </template>
            </div>

            <div class="glass-card p-4 space-y-1.5">
                <x-label for="justificacion" value="Justificación" />
                <textarea
                    id="justificacion"
                    name="justificacion"
                    rows="4"
                    maxlength="2000"
                    placeholder="Cuéntanos brevemente el motivo de tu salida..."
                    class="w-full rounded-xl border-ocean-200 dark:border-white/15 bg-white/70 dark:bg-white/10 dark:text-white backdrop-blur focus:border-ocean-500 focus:ring-ocean-500 shadow-sm placeholder:text-gray-400 dark:placeholder:text-white/40"
                >{{ old('justificacion') }}</textarea>
            </div>

            <div class="glass-card p-4 space-y-2">
                <x-label value="Adjunto (opcional según motivo)" />

                <label
                    for="adjunto_inicial_path"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-ocean-200 dark:border-white/15 bg-white/40 dark:bg-white/5 py-6 text-center cursor-pointer hover:border-ocean-400 dark:hover:border-ocean-400/60 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7 text-ocean-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                    </svg>
                    <span class="text-sm font-medium text-ocean-700 dark:text-ocean-200" x-text="archivo ?? 'Toca para adjuntar un archivo'"></span>
                    <span class="text-xs text-gray-400 dark:text-ocean-100/40">Máximo 10 MB</span>
                    <input
                        type="file"
                        id="adjunto_inicial_path"
                        name="adjunto_inicial_path"
                        class="hidden"
                        @change="archivo = $event.target.files[0]?.name ?? null"
                    >
                </label>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-ocean w-full text-sm py-3">
                    Crear papeleta
                </button>
            </div>
        </form>
    </div>
</x-trabajador-layout>
