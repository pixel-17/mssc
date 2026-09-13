<x-guest-layout>
    <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gradient-to-br from-ocean-50 via-white to-ocean-100 dark:from-ocean-950 dark:via-ocean-950 dark:to-black">
        <div class="glass-card w-full max-w-md p-8 text-center">
            <p class="text-6xl font-extrabold tracking-tight text-ocean-500 dark:text-ocean-400 mb-2">{{ $codigo }}</p>

            <h1 class="text-lg font-bold text-ocean-950 dark:text-white mb-2">{{ $titulo }}</h1>

            <p class="text-sm text-gray-500 dark:text-ocean-100/60 mb-6">{{ $mensaje }}</p>

            <a href="{{ url('/') }}" class="btn-ocean inline-flex items-center justify-center w-full text-sm py-3">
                Volver al inicio
            </a>
        </div>
    </div>
</x-guest-layout>
