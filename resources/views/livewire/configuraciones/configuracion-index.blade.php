<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Configuraciones" />

        <x-admin.mensajes />

        {{-- Bloque de almuerzo: inicio y fin se muestran y editan juntos, en un solo modal. --}}
        <div class="space-y-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-tinta-500 dark:text-tinta-200">
                Bloque de almuerzo
            </h3>

            <x-admin.tabla :columnas="['Clave', 'Valor', 'Descripción', '']">
                <tr wire:key="bloque-almuerzo">
                    <td class="px-4 py-3 font-semibold">BLOQUE_ALMUERZO_INICIO / FIN</td>
                    <td class="px-4 py-3">{{ $bloqueAlmuerzoInicio }} – {{ $bloqueAlmuerzoFin }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Bloque de almuerzo para el descuento de refrigerio (solo 276).
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <x-admin.accion-editar href="#" wire:click.prevent="editarBloqueAlmuerzo" />
                    </td>
                </tr>
            </x-admin.tabla>
        </div>

        @foreach ($grupos as $nombreGrupo => $configuraciones)
            <div class="space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-tinta-500 dark:text-tinta-200">
                    {{ $nombreGrupo }}
                </h3>

                <x-admin.tabla :columnas="['Clave', 'Valor', 'Descripción', '']">
                    @foreach ($configuraciones as $configuracion)
                        <tr wire:key="config-{{ $configuracion->id }}">
                            <td class="px-4 py-3 font-semibold">{{ $configuracion->clave }}</td>
                            <td class="px-4 py-3">{{ $configuracion->valorFormateado() }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $configuracion->descripcion }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <x-admin.accion-editar href="#" wire:click.prevent="editar({{ $configuracion->id }})" />
                            </td>
                        </tr>
                    @endforeach
                </x-admin.tabla>
            </div>
        @endforeach
    </div>

    {{-- Modal glassmorphism de edición: reemplaza la navegación a /configuraciones/{id}/editar. --}}
    <x-dialog-modal wire:model.live="modalAbierto">
        <x-slot name="title">
            {{ $esBloqueAlmuerzo ? 'Editar bloque de almuerzo' : 'Editar configuración' }}
        </x-slot>

        <x-slot name="content">
            @if ($esBloqueAlmuerzo)
                <div class="space-y-4">
                    <x-admin.campo label="Inicio" for="modal-bloque-inicio" campo="bloqueInicio"
                        ayuda="Formato 24 horas (HH:MM), por ejemplo 13:00.">
                        <x-input id="modal-bloque-inicio" type="time" wire:model="bloqueInicio" class="w-full" />
                    </x-admin.campo>

                    <x-admin.campo label="Fin" for="modal-bloque-fin" campo="bloqueFin"
                        ayuda="Debe ser posterior al inicio.">
                        <x-input id="modal-bloque-fin" type="time" wire:model="bloqueFin" class="w-full" />
                    </x-admin.campo>
                </div>
            @else
                <div class="space-y-4">
                    <div>
                        <x-label for="modal-clave" value="Clave" class="block mb-1" />
                        <x-input id="modal-clave" type="text" :value="$claveEditando" disabled class="w-full opacity-60" />
                    </div>

                    @switch($this->tipoCampoDeClaveEditando())
                        @case('hora')
                            <x-admin.campo label="Valor" for="modal-valor" campo="valor"
                                ayuda="Formato 24 horas (HH:MM), por ejemplo 07:45.">
                                <x-input id="modal-valor" type="time" wire:model="valor" class="w-full" />
                            </x-admin.campo>
                            @break

                        @case('entero')
                            <x-admin.campo label="Valor" for="modal-valor" campo="valor">
                                <x-input id="modal-valor" type="number" min="1" max="100000" step="1" wire:model="valor" class="w-full" />
                            </x-admin.campo>
                            @break

                        @case('dias')
                            <div>
                                <x-label for="modal-valor" value="Días laborables" class="block mb-2" />
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    @foreach (\App\Livewire\Configuraciones\ConfiguracionForm::DIAS as $numero => $nombre)
                                        <x-admin.campo-checkbox :for="'modal-dia-' . $numero" :label="$nombre"
                                            :value="$numero" wire:model.live="diasSeleccionados" />
                                    @endforeach
                                </div>
                                <x-input-error for="valor" class="mt-2" />
                            </div>
                            @break

                        @default
                            <x-admin.campo label="Valor" for="modal-valor" campo="valor">
                                <x-input id="modal-valor" type="text" wire:model="valor" class="w-full" />
                            </x-admin.campo>
                    @endswitch

                    <div>
                        <x-label for="modal-descripcion" value="Descripción" class="block mb-1" />
                        <x-input id="modal-descripcion" type="text" :value="$descripcionEditando" disabled class="w-full opacity-60" />
                    </div>
                </div>
            @endif
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cerrarModal" wire:loading.attr="disabled">
                Cancelar
            </x-secondary-button>

            <x-button class="ms-3" wire:click="guardar" wire:loading.attr="disabled">
                Guardar
            </x-button>
        </x-slot>
    </x-dialog-modal>
</div>