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
