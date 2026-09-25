{{-- Raíz única (Livewire): ms-auto la empuja a la derecha del enlace del sidebar. --}}
<span class="ms-auto">
    @if ($cantidad > 0)
        <span class="sidebar-badge">
            <span aria-hidden="true">{{ $cantidad > 99 ? '99+' : $cantidad }}</span>
            <span class="sr-only">{{ $cantidad }} {{ $cantidad === 1 ? 'papeleta pendiente en tu bandeja' : 'papeletas pendientes en tu bandeja' }}</span>
        </span>
    @endif
</span>
