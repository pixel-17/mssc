<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 relative overflow-hidden bg-ocean-gradient">
    {{-- Blobs decorativos --}}
    <div class="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full bg-ocean-300/30 blur-3xl animate-float-slow"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-16 size-96 rounded-full bg-ocean-500/30 blur-3xl animate-float-slow" style="animation-delay:2s"></div>
    <div class="pointer-events-none absolute top-1/3 right-1/4 size-40 rounded-full bg-white/10 blur-2xl"></div>

    <div class="relative z-10">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md mt-8 px-6 py-8 sm:px-10 glass-strong rounded-3xl animate-fade-in-up">
        {{ $slot }}
    </div>

    <p class="relative z-10 mt-8 text-xs text-white/60 text-center px-6">
        &copy; {{ date('Y') }} Municipalidad de Santiago de Cusco — Sistema de Gestión de Papeletas
    </p>
</div>
