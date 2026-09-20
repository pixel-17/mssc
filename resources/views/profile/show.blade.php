<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-tinta-950 leading-tight tracking-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')

                <x-section-border />
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.update-password-form')
                </div>

                <x-section-border />
            @endif

            <div class="mt-10 sm:mt-0">
                @livewire('profile.device-settings-form')
            </div>

            <x-section-border />

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.two-factor-authentication-form')
                </div>

                <x-section-border />
            @endif

            <div class="mt-10 sm:mt-0">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-section-border />

                <div class="mt-10 sm:mt-0">
                    @livewire('profile.delete-user-form')
                </div>
            @endif

            <x-section-border />

            {{--
                Cerrar sesión: el trabajador no tiene sidebar, así que esta
                (junto al botón de la barra superior) es su única salida.
                No confundir con "cerrar las otras sesiones" de arriba, que
                deja esta abierta.
            --}}
            <div class="mt-10 sm:mt-0">
                <x-action-section>
                    <x-slot name="title">
                        Cerrar sesión
                    </x-slot>

                    <x-slot name="description">
                        Sales de este dispositivo. Tus papeletas y tu historial quedan guardados.
                    </x-slot>

                    <x-slot name="content">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-button type="submit">
                                Cerrar sesión
                            </x-button>
                        </form>
                    </x-slot>
                </x-action-section>
            </div>
        </div>
    </div>
</x-app-layout>
