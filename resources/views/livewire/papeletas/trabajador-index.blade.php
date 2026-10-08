<div class="space-y-5">
    {{-- Mi turno / Todas: por defecto solo se ven las papeletas del turno en curso. --}}
    <div class="space-y-1">
        <div class="inline-flex rounded-lg overflow-hidden border border-gray-300 dark:border-white/15" role="group" aria-label="Qué papeletas ver">
            <button type="button" wire:click="$set('verTodas', false)" aria-pressed="{{ $verTodas ? 'false' : 'true' }}"
                    class="px-4 py-2 text-sm font-medium transition {{ $verTodas ? 'bg-white dark:bg-white/5 text-gray-700 dark:text-tinta-50/80 hover:bg-gray-50 dark:hover:bg-white/10' : 'bg-tinta-600 text-white' }}">
                Mi turno
            </button>
            <button type="button" wire:click="$set('verTodas', true)" aria-pressed="{{ $verTodas ? 'true' : 'false' }}"
                    class="px-4 py-2 text-sm font-medium transition border-l border-gray-300 dark:border-white/15 {{ $verTodas ? 'bg-tinta-600 text-white' : 'bg-white dark:bg-white/5 text-gray-700 dark:text-tinta-50/80 hover:bg-gray-50 dark:hover:bg-white/10' }}">
                Todas mis papeletas
            </button>
        </div>
        @unless ($verTodas)
            <p class="text-xs text-gray-500 dark:text-tinta-100/60">Se muestran las papeletas de tu turno actual; salen de aquí cuando el turno termina.</p>
        @endunless
    </div>

    <div>
        <label for="trabajador-index-buscar" class="block text-sm font-medium mb-1 text-gray-700 dark:text-tinta-50/80">Buscar papeleta</label>
        <input id="trabajador-index-buscar" type="text" wire:model.live.debounce.300ms="buscar" placeholder="Motivo o justificación..."
               class="w-full rounded-md border-gray-300 dark:border-white/15 dark:bg-white/5 dark:text-white shadow-sm text-sm focus:border-tinta-500 focus:ring-tinta-500">
    </div>

    @if ($papeletas->isEmpty() && $buscar !== '')
        <div class="glass-card p-6 text-center text-sm text-gray-500 dark:text-tinta-100/60">Ninguna papeleta coincide con la búsqueda.</div>
    @elseif ($papeletas->isEmpty() && ! $verTodas && $tieneAlguna)
        <div class="glass-card p-8 text-center space-y-3">
            <p class="font-semibold text-tinta-950 dark:text-white">No tienes papeletas en tu turno actual</p>
            <p class="text-sm text-gray-500 dark:text-tinta-100/60">Las papeletas de turnos anteriores siguen disponibles en tu historial.</p>
            <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                <button type="button" wire:click="$set('verTodas', true)" class="btn-primary inline-flex">Ver todas mis papeletas</button>
                <a href="{{ route('trabajador.papeletas.create') }}" class="inline-flex text-sm font-medium text-tinta-600 dark:text-tinta-300 hover:underline px-3 py-2">Crear papeleta</a>
            </div>
        </div>
    @elseif ($papeletas->isEmpty())
        <div class="glass-card p-8 text-center space-y-3">
            <div class="mx-auto icon-chip !size-14 !bg-tinta-500/15 !text-tinta-600 dark:!text-tinta-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                </svg>
            </div>
            <p class="font-semibold text-tinta-950 dark:text-white">Todavía no tienes papeletas</p>
            <p class="text-sm text-gray-500 dark:text-tinta-100/60">Cuando pidas un permiso de salida, aparecerá aquí con su estado en vivo.</p>
            <a href="{{ route('trabajador.papeletas.create') }}" class="btn-primary inline-flex mt-2">
                Crear tu primera papeleta
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($papeletas as $papeleta)
                <div wire:key="papeleta-{{ $papeleta->id }}">
                    <x-papeleta-ticket :papeleta="$papeleta" />
                </div>
            @endforeach
        </div>

        <div class="pt-1">
            {{ $papeletas->links() }}
        </div>
    @endif
</div>
