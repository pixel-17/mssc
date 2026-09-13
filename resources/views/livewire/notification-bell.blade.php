<div x-data="{ open: false }" class="relative">
    <button
        @click="open = ! open"
        type="button"
        class="relative inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none transition"
        aria-label="{{ __('Notificaciones') }}"
    >
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>

        @if ($noLeidas > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center size-4 rounded-full bg-amber-500 text-white text-[10px] font-semibold leading-none">
                {{ $noLeidas > 9 ? '9+' : $noLeidas }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5"
        style="display: none;"
    >
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
            <span class="text-xs font-semibold text-gray-500 uppercase">{{ __('Notificaciones') }}</span>

            @if ($noLeidas > 0)
                <button
                    type="button"
                    wire:click="marcarTodasComoLeidas"
                    class="text-xs text-amber-600 hover:text-amber-700"
                >
                    {{ __('Marcar todas como leídas') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
            @forelse ($notificaciones as $notificacion)
                <a
                    href="{{ $notificacion->data['url'] ?? '#' }}"
                    wire:click="marcarComoLeida('{{ $notificacion->id }}')"
                    class="block px-4 py-3 text-sm hover:bg-gray-50 {{ $notificacion->read_at ? 'bg-white' : 'bg-amber-50' }}"
                >
                    <p class="font-medium text-gray-800">{{ $notificacion->data['titulo'] ?? '' }}</p>
                    <p class="text-gray-500 mt-0.5">{{ $notificacion->data['mensaje'] ?? '' }}</p>
                    <p class="text-gray-400 text-xs mt-1">{{ $notificacion->created_at->diffForHumans() }}</p>
                </a>
            @empty
                <p class="px-4 py-6 text-sm text-gray-400 text-center">{{ __('Sin notificaciones por ahora.') }}</p>
            @endforelse
        </div>
    </div>
</div>
