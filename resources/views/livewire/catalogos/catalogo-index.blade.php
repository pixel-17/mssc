<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <x-admin.encabezado titulo="Catálogos" />

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Acceso rápido a los catálogos que usa el sistema de papeletas.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($catalogos as $catalogo)
                <a wire:key="catalogo-index-a-{{ $loop->index }}" href="{{ $catalogo['ruta'] }}" class="glass-card p-5 flex items-start gap-4 hover:opacity-90 transition">
                    <span class="shrink-0 size-10 rounded-lg bg-tinta-50 dark:bg-white/10 text-tinta-700 dark:text-tinta-200 flex items-center justify-center">
                        <x-icon :name="$catalogo['icono']" class="size-5" />
                    </span>
                    <span>
                        <span class="block font-semibold text-tinta-950 dark:text-white">{{ $catalogo['nombre'] }}</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $catalogo['descripcion'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</div>
