<x-guest-layout>
    <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gradient-to-br from-tinta-50 via-white to-tinta-100 dark:from-tinta-950 dark:via-tinta-950 dark:to-black">
        <div class="glass-card w-full max-w-md p-8 text-center">
            <p class="text-6xl font-extrabold tracking-tight text-tinta-500 dark:text-tinta-400 mb-2">{{ $codigo }}</p>

            <h1 class="text-lg font-bold text-tinta-950 dark:text-white mb-2">{{ $titulo }}</h1>

            <p class="text-sm text-gray-500 dark:text-tinta-100/60 mb-6">{{ $mensaje }}</p>

            <a href="{{ url('/') }}" class="btn-primary inline-flex items-center justify-center w-full text-sm py-3">
                Volver al inicio
            </a>
        </div>
    </div>
</x-guest-layout>
