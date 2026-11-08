<div>
    <div @class(['space-y-6', 'max-w-3xl mx-auto py-10 sm:px-6 lg:px-8' => ! $embebido])>
        @unless ($embebido)
            <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
                Configurar turno — {{ $trabajador->name }} {{ $trabajador->apellido }}
            </h2>
        @endunless

        @if ($mensajeExito)
            <div class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 dark:bg-emerald-500/10 dark:border-emerald-500/30">
                <p class="text-sm text-emerald-800 dark:text-emerald-300">{{ $mensajeExito }}</p>
            </div>
        @endif

        @if ($sinTurnoVigenteHoy)
            <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 dark:bg-amber-500/10 dark:border-amber-500/30">
                <p class="text-sm text-amber-800 dark:text-amber-300">
                    {{ $trabajador->name }} no tiene un turno vigente cargado para hoy y no podrá crear papeletas
                    hasta que se le asigne uno.
                </p>
            </div>
        @endif

        {{-- Esta pantalla es solo para régimen 276 (mount() aborta con 403
             para cualquier otro); el ciclo lo define el Admin globalmente,
             así que acá no hay nada que editar: calendario del mes (en
             blanco si no se generó, coloreado si ya se generó) y el botón. --}}
        <div @class(['space-y-4', 'glass-card p-6' => ! $embebido])>
            @include('livewire.turnos.partials.calendario-mes')

            <div class="flex items-center justify-end gap-3 border-t pt-4 dark:border-gray-700">
                @if ($embebido)
                    <button type="button" wire:click="cancelar" class="text-sm text-gray-500">Cancelar</button>
                @else
                    <a href="{{ route('turnos.index') }}" class="text-sm text-gray-500">Cancelar</a>
                @endif
                <button type="button" wire:click="guardar" wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 bg-tinta-800 text-white rounded-md text-sm disabled:opacity-50">
                    <span wire:loading wire:target="guardar">Generando…</span>
                    <span wire:loading.remove wire:target="guardar">Generar horario de este mes</span>
                </button>
            </div>
        </div>
    </div>
</div>