<x-trabajador-layout titulo="Nueva papeleta" :volver-a="route('trabajador.papeletas.index')">
    <div x-data="{ motivoId: '{{ old('motivo_id') }}', motivos: {{ $motivos->toJson() }}, archivo: null, errorArchivo: null, enviando: false,
        elegirArchivo(ev) {
            const f = ev.target.files[0] ?? null;
            this.errorArchivo = null;
            if (f && f.size > 10 * 1024 * 1024) {
                this.errorArchivo = 'El archivo pesa más de 10 MB. Elige uno más liviano.';
                ev.target.value = '';
                this.archivo = null;
                return;
            }
            this.archivo = f ? f.name : null;
        },
        get pideAdjunto() { const m = this.motivos.find(m => m.id == this.motivoId); return !m || m.adjunto !== 'no'; } }">
        <x-flash-messages />

        {{--
            Una sola hoja, no cuatro tarjetas apiladas: como llenar un
            formulario de papel, campo tras campo, separados por una
            línea — no por una tarjeta con sombra cada uno. El número en
            serif marca el orden real de llenado (motivo → justificación
            → hora → adjunto), no es decoración.
        --}}
        <form method="POST" action="{{ route('trabajador.papeletas.store') }}" enctype="multipart/form-data" class="mt-4"
              @submit="enviando = true" @pageshow.window="enviando = false">
            @csrf

            <div class="glass-card divide-y divide-tinta-100/70 dark:divide-white/10 px-4 sm:px-5">

                <div class="py-4">
                    <div class="flex items-baseline gap-2.5">
                        <span class="font-display text-sello-500 dark:text-sello-300 font-semibold shrink-0">1</span>
                        <div class="w-full space-y-1.5">
                            <x-label for="motivo_id" value="Motivo" />
                            <select
                                id="motivo_id"
                                name="motivo_id"
                                x-model="motivoId"
                                required
                                class="input-glass @error('motivo_id') !border-alarma-500 @enderror"
                            >
                                <option value="">Selecciona un motivo</option>
                                @foreach ($motivos as $motivo)
                                    <option value="{{ $motivo->id }}">{{ $motivo->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="motivo_id" class="mt-1" />

                            <template x-if="motivoId">
                                <template x-for="m in motivos.filter(m => m.id == motivoId)" :key="m.id">
                                    <ul class="text-xs text-gray-500 dark:text-tinta-100/60 space-y-1 pt-2">
                                        <li x-show="m.adjunto === 'obligatorio'">Este motivo exige adjuntar sustento.</li>
                                        <li x-show="m.requiere_sustento_en_retorno">Al retornar deberás presentar sustento dentro del plazo configurado.</li>
                                    </ul>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="py-4">
                    <div class="flex items-baseline gap-2.5">
                        <span class="font-display text-sello-500 dark:text-sello-300 font-semibold shrink-0">2</span>
                        <div class="w-full space-y-1.5">
                            <x-label for="justificacion" value="Justificación" />
                            <textarea
                                id="justificacion"
                                name="justificacion"
                                rows="4"
                                maxlength="2000"
                                placeholder="Cuéntanos brevemente el motivo de tu salida..."
                                class="input-glass @error('justificacion') !border-alarma-500 @enderror"
                            >{{ old('justificacion') }}</textarea>
                            <x-input-error for="justificacion" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="py-4">
                    <div class="flex items-baseline gap-2.5">
                        <span class="font-display text-sello-500 dark:text-sello-300 font-semibold shrink-0">3</span>
                        <div class="w-full space-y-1.5">
                            <x-label for="hora_retorno_estimado" value="Hora de retorno estimada (opcional)" />
                            {{--
                                BUG: min/max se calculaban con now() en el
                                servidor (config('app.timezone') = UTC), pero
                                el <input type="datetime-local"> los compara
                                contra la hora LOCAL del navegador. Con Perú
                                en UTC-5, el min quedaba 5 horas adelantado
                                respecto al reloj real del trabajador, así
                                que cualquier hora "de ahora" que intentaba
                                elegir caía antes del min y el navegador
                                bloqueaba con "El valor debe ser igual o
                                posterior a...". Se calcula ahora en JS con
                                la hora local real del dispositivo.
                            --}}
                            <input
                                type="datetime-local"
                                id="hora_retorno_estimado"
                                name="hora_retorno_estimado"
                                value="{{ old('hora_retorno_estimado') }}"
                                x-data="{ regimen: '{{ auth()->user()->regimen }}' }"
                                x-init="
                                    const pad = (n) => String(n).padStart(2, '0');
                                    const aFormatoLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                                    const ahora = new Date();
                                    $el.min = aFormatoLocal(ahora);
                                    const limite = new Date(ahora);
                                    if (regimen === '728') limite.setDate(limite.getDate() + 1);
                                    limite.setHours(23, 59, 0, 0);
                                    $el.max = aFormatoLocal(limite);
                                "
                                class="input-glass @error('hora_retorno_estimado') !border-alarma-500 @enderror"
                            >
                            <p class="text-xs text-gray-500 dark:text-tinta-100/60">Solo informativa, para que tu jefe sepa cuándo esperarte.</p>
                            <x-input-error for="hora_retorno_estimado" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="py-4" x-show="pideAdjunto">
                    <div class="flex items-baseline gap-2.5">
                        <span class="font-display text-sello-500 dark:text-sello-300 font-semibold shrink-0">4</span>
                        <div class="w-full space-y-2">
                            <x-label for="adjunto_inicial_path" value="Adjunto" />
                            <p x-show="motivos.find(m => m.id == motivoId)?.requiere_sustento_en_retorno" x-cloak class="text-xs text-gray-500 dark:text-tinta-100/60">
                                Si lo adjuntas ahora, cuenta como tu justificación y RR. HH. la revisa cuando termine tu salida. Si no puedes, podrás presentarla después, dentro del plazo.
                            </p>

                            <label
                                for="adjunto_inicial_path"
                                class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed @error('adjunto_inicial_path') border-alarma-400 @else border-tinta-200 @enderror dark:border-white/15 bg-tinta-50/40 dark:bg-white/5 py-6 text-center cursor-pointer hover:border-sello-400 dark:hover:border-sello-400/60 focus-within:border-sello-500 focus-within:ring-2 focus-within:ring-sello-300 transition"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7 text-tinta-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                                </svg>
                                <span class="text-sm font-medium text-tinta-700 dark:text-tinta-200" x-text="archivo ?? 'Toca para adjuntar un archivo'"></span>
                                <span class="text-xs text-gray-500 dark:text-tinta-100/60">PDF, JPG o PNG · máximo 10 MB</span>
                                <input
                                    type="file"
                                    id="adjunto_inicial_path"
                                    name="adjunto_inicial_path"
                                    :disabled="!pideAdjunto"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    class="sr-only"
                                    @change="elegirArchivo($event)"
                                >
                            </label>
                            <p x-show="errorArchivo" x-cloak x-text="errorArchivo" role="alert" class="mt-1 text-sm text-red-600"></p>
                            <x-input-error for="adjunto_inicial_path" class="mt-1" />
                        </div>
                    </div>
                </div>

            </div>

            <div class="pt-4">
                <button type="submit" x-bind:disabled="enviando" class="btn-primary w-full text-sm py-3 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="! enviando">Crear papeleta</span>
                    <span x-show="enviando" x-cloak>Enviando…</span>
                </button>
            </div>
        </form>
    </div>
</x-trabajador-layout>
