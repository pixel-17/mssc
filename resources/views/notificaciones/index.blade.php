<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 tracking-tight">Avisos</h2>
            <button
                x-data
                @click="$store.notificaciones.marcarTodas(); window.location.reload()"
                x-show="$store.notificaciones.noLeidas > 0"
                x-cloak
                class="text-sm font-semibold text-azul-600 hover:text-sello-600 transition"
            >
                Marcar todas leídas
            </button>
        </div>
    </x-slot>

    <div class="space-y-2.5 stagger">
        @forelse ($notificaciones as $notificacion)
            <a
                href="{{ $notificacion->papeleta_id ? route('papeletas.show', $notificacion->papeleta_id) : '#' }}"
                @if(! $notificacion->leida_at)
                    onclick="fetch('{{ route('notificaciones.leida', $notificacion) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}})"
                @endif
                class="glass-card flex items-start gap-3 p-4 {{ $notificacion->leida_at ? '' : 'border-l-4 !border-l-sello-500' }}"
            >
                <div class="w-9 h-9 rounded-xl bg-azul-50 flex items-center justify-center text-azul-600 shrink-0">
                    <x-icon name="bell" class="w-4 h-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $notificacion->titulo }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $notificacion->mensaje }}</p>
                    <p class="text-[11px] text-gray-400 mt-1">{{ $notificacion->created_at->diffForHumans() }}</p>
                </div>
                @if(! $notificacion->leida_at)
                    <span class="w-2 h-2 rounded-full bg-sello-500 shrink-0 mt-1.5"></span>
                @endif
            </a>
        @empty
            <div class="glass-card p-12 text-center animate-fade-in-up">
                <x-icon name="bell" class="w-12 h-12 mx-auto text-gray-300" />
                <p class="text-gray-500 mt-3 text-sm">Sin notificaciones por ahora.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $notificaciones->links() }}
    </div>
</x-app-layout>
