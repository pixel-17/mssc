@php
    $perfil = auth()->user();

    $secciones = array_values(array_filter([
        ['id' => 'informacion', 'ver' => Laravel\Fortify\Features::canUpdateProfileInformation()],
        ['id' => 'contrasena',  'ver' => Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords())],
        ['id' => 'dispositivo', 'ver' => true],
        ['id' => 'seguridad',   'ver' => Laravel\Fortify\Features::canManageTwoFactorAuthentication()],
        ['id' => 'sesiones',    'ver' => true],
        ['id' => 'eliminar',    'ver' => Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures()],
        ['id' => 'salir',       'ver' => true],
    ], fn ($s) => $s['ver']));

    $ver = fn (string $id) => in_array($id, array_column($secciones, 'id'), true);
@endphp

<x-app-layout title="Perfil">
    {{-- Sin slot `header`: la cabecera de identidad vive dentro del mismo
         contenedor que las secciones, así todo comparte el mismo ancho
         y se alinea perfectamente. Sin menú de navegación: solo la
         cabecera y las secciones, una debajo de otra. --}}
    <div class="mx-auto w-full max-w-3xl">

        {{-- ── Cabecera de identidad ───────────────────────────── --}}
        <x-tarjeta-identidad :user="$perfil" class="mb-6" />

        {{-- ── Secciones ────────────────────────────────────────── --}}
        <div class="min-w-0 flex-1 space-y-4">
            @if ($ver('informacion'))
                @livewire('profile.update-profile-information-form')
            @endif

            @if ($ver('contrasena'))
                @livewire('profile.update-password-form')
            @endif

            @livewire('profile.device-settings-form')

            @if ($ver('seguridad'))
                @livewire('profile.two-factor-authentication-form')
            @endif

            @livewire('profile.logout-other-browser-sessions-form')

            @if ($ver('eliminar'))
                @livewire('profile.delete-user-form')
            @endif

            {{-- Cerrar sesión --}}
            <x-action-section id="salir">
                <x-slot name="title">Cerrar sesión</x-slot>
                <x-slot name="description">Sales de este dispositivo. Tus papeletas y tu historial quedan guardados.</x-slot>
                <x-slot name="content">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" class="w-full sm:w-auto">Cerrar sesión</x-button>
                    </form>
                </x-slot>
            </x-action-section>
        </div>
    </div>
</x-app-layout>