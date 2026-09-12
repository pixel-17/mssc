<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="glass-card overflow-hidden animate-fade-in-up">
                <x-welcome />
            </div>
        </div>
    </div>
</x-app-layout>
