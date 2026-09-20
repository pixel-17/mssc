{{--
    Navegación inferior del shell móvil. Recibe $items desde
    layouts/app.blade.php. Estilos: .bottom-nav, .bottom-nav-item,
    .nav-badge en resources/css/app.css (activo en sello).
--}}
<nav class="bottom-nav" aria-label="Navegación principal">
    @foreach ($items as $item)
        <a
            href="{{ $item['route'] }}"
            @class(['bottom-nav-item', 'is-active' => $item['active']])
            @if ($item['active']) aria-current="page" @endif
        >
            <x-icon :name="$item['icon']" />
            <span>{{ $item['label'] }}</span>

            @if (($item['badge'] ?? 0) > 0)
                <span class="nav-badge">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
