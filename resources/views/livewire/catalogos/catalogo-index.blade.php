<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-bold text-2xl text-ocean-950 dark:text-white leading-tight tracking-tight">
            Catálogos
        </h2>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Se está migrando cada catálogo fuera de Filament, uno por uno. Los marcados como "en Filament" siguen funcionando igual que antes mientras se migran.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($catalogos as $catalogo)
                <a href="{{ $catalogo['ruta'] }}" class="glass-card p-5 flex items-center justify-between hover:opacity-90 transition">
                    <span class="font-semibold text-ocean-950 dark:text-white">{{ $catalogo['nombre'] }}</span>

                    @if ($catalogo['listo'])
                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                            Listo
                        </span>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300">
                            En Filament
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
