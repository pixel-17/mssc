<div>
    @if ($abierto && $usuario)
        <x-organigrama.drawer :titulo="'Dar de baja a '.$usuario->nombre_completo"
                              subtitulo="Decide qué pasa con su jefatura"
                              icono="user-circle">
            @if ($error)
                <div class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200" role="alert">
                    <span class="mt-1 inline-block size-2 shrink-0 rounded-full bg-red-500"></span>
                    <span>{{ $error }}</span>
                </div>
            @endif

            <div class="rounded-xl bg-amber-50 px-3 py-2.5 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200" role="status">
                Tiene gente a su cargo. Elige un reemplazo o déjala sin jefe. Las papeletas abiertas pasan al jefe nuevo.
            </div>

            @foreach ($filas as $fila)
                @php($uid = $fila['unidad']->id)
                <section class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10" wire:key="baja-{{ $uid }}">
                    <div>
                        <h3 class="text-sm font-semibold text-tinta-950 dark:text-white">{{ $fila['unidad']->nombre }}</h3>
                        <p class="text-xs text-gray-500 dark:text-tinta-50/60">
                            {{ $fila['es_area'] ? 'Jefe de área' : 'Jefe inmediato' }} ·
                            {{ $fila['personas'] }} {{ $fila['personas'] === 1 ? 'persona' : 'personas' }}
                            @if ($fila['subunidades'] > 0) · {{ $fila['subunidades'] }} {{ $fila['subunidades'] === 1 ? 'sub-unidad' : 'sub-unidades' }} @endif
                            @if ($fila['pendientes'] > 0) · {{ $fila['pendientes'] }} {{ $fila['pendientes'] === 1 ? 'papeleta pendiente' : 'papeletas pendientes' }} @endif
                        </p>
                    </div>

                    <div class="space-y-3 text-sm">
                        <label class="flex items-start gap-2">
                            <input type="radio" value="existente" wire:model.live="decisiones.{{ $uid }}.modo" class="mt-1">
                            <span>Asignar a una persona que ya existe</span>
                        </label>
                        @if (($decisiones[$uid]['modo'] ?? '') === 'existente')
                            <div class="ms-6">
                                <x-organigrama.combobox :model="'decisiones.'.$uid.'.usuario_id'" :options="$candidatos" placeholder="Buscar por nombre…" vacio="Elegir reemplazo…" :limpiable="false" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-tinta-50/60">Si viene de otra unidad, pasa a esta.</p>
                            </div>
                        @endif

                        <label class="flex items-start gap-2">
                            <input type="radio" value="nuevo" wire:model.live="decisiones.{{ $uid }}.modo" class="mt-1">
                            <span>Crear un jefe nuevo</span>
                        </label>
                        @if (($decisiones[$uid]['modo'] ?? '') === 'nuevo')
                            <div class="ms-6 grid grid-cols-2 gap-2">
                                <x-input type="text" wire:model="decisiones.{{ $uid }}.nuevo.name" placeholder="Nombre" class="w-full" />
                                <x-input type="text" wire:model="decisiones.{{ $uid }}.nuevo.apellido" placeholder="Apellido" class="w-full" />
                                <x-input type="text" wire:model="decisiones.{{ $uid }}.nuevo.dni" placeholder="DNI" class="w-full" />
                                <x-select wire:model="decisiones.{{ $uid }}.nuevo.regimen">
                                    <option value="276">Régimen 276</option>
                                    <option value="728">Régimen 728</option>
                                </x-select>
                                <x-input type="email" wire:model="decisiones.{{ $uid }}.nuevo.email" placeholder="Correo" class="col-span-2 w-full" />
                                <p class="col-span-2 text-xs text-gray-500 dark:text-tinta-50/60">Su contraseña inicial es su DNI.</p>
                            </div>
                        @endif

                        <label class="flex items-start gap-2">
                            <input type="radio" value="vacante" wire:model.live="decisiones.{{ $uid }}.modo" class="mt-1">
                            <span>Dejar sin jefe</span>
                        </label>
                        @if (($decisiones[$uid]['modo'] ?? '') === 'vacante')
                            <p class="ms-6 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                                @if ($fila['es_area'])
                                    Sin jefe de área no podrán crear papeletas sus propios trabajadores ni los jefes inmediatos del área. Los trabajadores de esos jefes no se ven afectados.
                                @else
                                    Sus trabajadores no podrán crear papeletas hasta que haya un jefe.
                                @endif
                            </p>
                        @endif
                    </div>
                </section>
            @endforeach

            <x-slot:pie>
                <button type="button" @click="cerrar()" class="btn-secondary text-sm">Cancelar</button>
                <button type="button" wire:click="confirmar" wire:loading.attr="disabled" wire:target="confirmar" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Dar de baja
                </button>
            </x-slot:pie>
        </x-organigrama.drawer>
    @endif
</div>
