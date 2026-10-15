@php
    $perfil = auth()->user();

    $secciones = array_values(array_filter([
        ['id' => 'informacion', 'label' => 'Información',             'ver' => Laravel\Fortify\Features::canUpdateProfileInformation()],
        ['id' => 'contrasena',  'label' => 'Contraseña',              'ver' => Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords())],
        ['id' => 'dispositivo', 'label' => 'Dispositivo',             'ver' => true],
        ['id' => 'seguridad',   'label' => 'Verificación en 2 pasos', 'ver' => Laravel\Fortify\Features::canManageTwoFactorAuthentication()],
        ['id' => 'sesiones',    'label' => 'Sesiones',                'ver' => true],
        ['id' => 'eliminar',    'label' => 'Eliminar cuenta',         'ver' => Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures()],
        ['id' => 'salir',       'label' => 'Cerrar sesión',           'ver' => true],
    ], fn ($s) => $s['ver']));

    $ver = fn (string $id) => in_array($id, array_column($secciones, 'id'), true);
@endphp

<x-app-layout>
    {{-- Sin slot `header`: la tarjeta de identidad vive DENTRO del mismo
         contenedor que el grid, así ambos comparten el mismo ancho y se
         alinean perfectamente. --}}

    {{-- Wrapper único: en escritorio ocupa todo el app-content; en lg+
         se convierte en el grid de tres zonas:
         [índice 13rem] [espacio 2rem] [contenido, máx. 56rem]  --}}
    <div class="mx-auto w-full max-w-4xl">

        {{-- ── Tarjeta de identidad ───────────────────────────── --}}
        <x-tarjeta-identidad :user="$perfil" class="mb-6" />

        {{-- ── Grid índice + secciones ───────────────────────── --}}
        <div class="lg:flex lg:items-start lg:gap-8"
             x-data="{ activo: @js($secciones[0]['id'] ?? '') }"
             x-init="$nextTick(() => {
                 const ids = @js(array_column($secciones, 'id'));
                 const obs = new IntersectionObserver(
                     es => es.forEach(e => { if (e.isIntersecting) activo = e.target.id }),
                     { rootMargin: '-10% 0px -65% 0px' }
                 );
                 ids.forEach(id => { const el = document.getElementById(id); if (el) obs.observe(el); });
             })">

            {{-- Índice lateral: sticky, solo lg+ --}}
            <nav aria-label="Secciones del perfil"
                 class="sticky top-20 hidden shrink-0 lg:block" style="width: 13rem;">
                <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-tinta-400 dark:text-zinc-500">
                    Perfil
                </p>
                <ul class="space-y-0.5">
                    @foreach ($secciones as $item)
                        <li>
                            <a href="#{{ $item['id'] }}"
                               x-on:click.prevent="
                                   activo = '{{ $item['id'] }}';
                                   document.getElementById('{{ $item['id'] }}')
                                       ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                               "
                               :class="activo === '{{ $item['id'] }}'
                                   ? 'bg-tinta-100 text-tinta-950 font-semibold dark:bg-white/10 dark:text-white'
                                   : 'text-tinta-600 hover:bg-tinta-50 hover:text-tinta-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-200'"
                               class="block rounded-lg px-3 py-1.5 text-sm transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-tinta-500">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Secciones --}}
            <div class="min-w-0 flex-1 space-y-5">
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
    </div>
</x-app-layout>
