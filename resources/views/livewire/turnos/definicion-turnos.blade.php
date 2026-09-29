<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Turnos" />

        <x-admin.mensajes />

        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aquí solo se define a qué hora empieza y termina cada turno. No se programa a ningún trabajador:
            eso lo hace cada jefe en su calendario de equipo, eligiendo entre estos turnos.
        </p>

        <form wire:submit="guardar" class="space-y-6">
            <x-admin.tabla :columnas="['Turno', 'Régimen', 'Desde', 'Hasta', '']">
                @foreach ($turnos as $codigo => $turno)
                    @php
                        $inicio = $horas[$codigo]['inicio'] ?? '';
                        $fin = $horas[$codigo]['fin'] ?? '';
                    @endphp
                    <tr wire:key="definicion-turno-{{ $codigo }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ \App\Support\TurnoColores::para($turno['sigla']) }}">
                                <span class="font-bold">{{ $turno['sigla'] }}</span> · {{ $turno['nombre'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $turno['regimen'] }}</td>
                        <td class="px-4 py-3">
                            <x-input id="definicion-turno-{{ $codigo }}-inicio" type="time" wire:model.live="horas.{{ $codigo }}.inicio" class="w-32" aria-label="Hora de inicio del turno {{ $turno['nombre'] }}" />
                            <x-input-error for="horas.{{ $codigo }}.inicio" class="mt-1" />
                        </td>
                        <td class="px-4 py-3">
                            <x-input id="definicion-turno-{{ $codigo }}-fin" type="time" wire:model.live="horas.{{ $codigo }}.fin" class="w-32" aria-label="Hora de fin del turno {{ $turno['nombre'] }}" />
                            <x-input-error for="horas.{{ $codigo }}.fin" class="mt-1" />
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            @if ($inicio !== '' && $fin !== '' && $fin < $inicio)
                                Termina al día siguiente
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-admin.tabla>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                El cambio vale para los horarios que se programen desde ahora. Los turnos ya guardados conservan las horas con las que se guardaron.
            </p>

            <div class="flex items-center justify-end gap-3">
                <x-button>Guardar</x-button>
            </div>
        </form>
    </div>
</div>
