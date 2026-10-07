<div>
    <div class="max-w-6xl mx-auto py-10 sm:px-6 lg:px-8 space-y-5">
        <x-admin.encabezado titulo="Papeletas" />

        {{-- Barra única: buscar, fecha y estado en un solo lugar --}}
        <div class="glass-card p-4 grid gap-4 md:grid-cols-12 items-end">
            <div class="md:col-span-5 relative">
                <label for="admin-papeletas-buscar" class="block text-xs font-medium mb-1 text-gray-600 dark:text-tinta-50/70">Buscar trabajador</label>
                <div class="relative">
                    <input id="admin-papeletas-buscar" type="search" autocomplete="off" wire:model.live.debounce.300ms="buscar"
                           placeholder="Nombre, apellido o DNI"
                           class="w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm pr-16 focus:border-tinta-500 focus:ring-tinta-500">
                    @if ($buscar !== '')
                        <button type="button" wire:click="limpiarBusqueda" class="absolute inset-y-0 right-2 my-auto h-6 text-xs text-gray-500 hover:text-gray-800 dark:text-tinta-100/60">Limpiar</button>
                    @endif
                </div>
                <span wire:loading wire:target="buscar" class="absolute -bottom-5 left-0 text-xs text-gray-400">Buscando…</span>
            </div>

            @unless ($trabajador)
                <div class="md:col-span-3">
                    <label for="admin-papeletas-fecha" class="block text-xs font-medium mb-1 text-gray-600 dark:text-tinta-50/70">Día</label>
                    <div class="flex gap-2">
                        <input id="admin-papeletas-fecha" type="date" wire:model.live="fecha"
                               class="w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500">
                        <button type="button" wire:click="volverAlDia" class="px-3 text-xs font-medium rounded-md border border-gray-300 dark:border-white/15 hover:bg-gray-50 dark:hover:bg-white/5">Hoy</button>
                    </div>
                </div>
            @endunless

            <div class="{{ $trabajador ? 'md:col-span-7' : 'md:col-span-4' }}">
                <label for="admin-papeletas-estado" class="block text-xs font-medium mb-1 text-gray-600 dark:text-tinta-50/70">Estado</label>
                <select id="admin-papeletas-estado" wire:model.live="estado"
                        class="w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500">
                    <option value="">Todos los estados</option>
                    @foreach ($estados as $clase => $etiqueta)
                        <option value="{{ $clase }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Resultados de la búsqueda: elegir a una persona para ver su historial --}}
        @if ($coincidencias->isNotEmpty())
            <div class="glass-card p-2">
                <p class="px-2 py-1 text-xs text-gray-500">Elige una persona para ver todo su historial</p>
                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($coincidencias as $persona)
                        <li wire:key="coincidencia-{{ $persona->id }}">
                            <button type="button" wire:click="verHistorial({{ $persona->id }})"
                                    class="w-full flex items-center justify-between px-2 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5 rounded">
                                <span class="text-sm text-gray-900 dark:text-white">{{ $persona->nombre_completo }}</span>
                                <span class="text-xs text-gray-500">DNI {{ $persona->dni }} · Ver historial →</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($trabajador === null && $buscar !== '')
            <p class="text-sm text-gray-500">Ninguna persona coincide con "{{ $buscar }}".</p>
        @endif

        {{-- Contexto del listado: qué se está viendo --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            @if ($trabajador)
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Historial de {{ $trabajador->nombre_completo }}</p>
                    <p class="text-xs text-gray-500">DNI {{ $trabajador->dni }} · {{ $papeletas->total() }} {{ \Illuminate\Support\Str::plural('papeleta', $papeletas->total()) }} en total</p>
                </div>
                <button type="button" wire:click="volverAlDia" class="text-xs font-medium text-tinta-600 dark:text-tinta-300 underline">Volver a las papeletas del día</button>
            @else
                <p class="text-sm text-gray-700 dark:text-tinta-50/80">
                    {{ $papeletas->total() }} {{ \Illuminate\Support\Str::plural('papeleta', $papeletas->total()) }}
                    el {{ \Carbon\Carbon::parse($fecha)->translatedFormat('l d \d\e F') }}
                </p>
            @endif
        </div>

        <x-admin.tabla :columnas="['Día', 'Trabajador', 'Motivo', 'Estado', '']">
            @forelse ($papeletas as $papeleta)
                <tr wire:key="admin-papeleta-{{ $papeleta->id }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ $papeleta->dia_operativo->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $papeleta->trabajador->nombre_completo }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $papeleta->motivo->nombre }}</td>
                    <td class="px-4 py-3 text-sm"><x-estado-papeleta :estado="$papeleta->estado" :abandono="$papeleta->esAbandono()" /></td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.papeletas.show', $papeleta) }}" class="text-xs font-medium text-tinta-600 dark:text-tinta-300">Ver detalle →</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center">
                        @if ($trabajador)
                            <p class="text-sm text-gray-500">Este trabajador no tiene papeletas con ese estado.</p>
                        @elseif ($buscar !== '')
                            <p class="text-sm text-gray-500">No hay papeletas de esa búsqueda el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}. Prueba otro día o busca el historial de la persona.</p>
                        @else
                            <p class="text-sm text-gray-500">No hay papeletas el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}.</p>
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-admin.tabla>

        <div>{{ $papeletas->links() }}</div>
    </div>
</div>
