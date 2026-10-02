<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-registro-700" role="status">
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('login') }}"
              x-data="{ verClave: false, enviando: false }"
              @submit="enviando = true" @pageshow.window="enviando = false">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus
                         autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <div class="relative mt-1">
                    <x-input id="password" class="block w-full pe-12" type="password" name="password" required
                             x-bind:type="verClave ? 'text' : 'password'" autocomplete="current-password" />
                    <button type="button" @click="verClave = ! verClave"
                            class="absolute inset-y-0 end-0 flex w-11 items-center justify-center rounded-e-xl text-tinta-500 hover:text-tinta-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-tinta-500"
                            :aria-pressed="verClave" :aria-label="verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <svg x-show="! verClave" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg x-show="verClave" x-cloak class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-tinta-700">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-x-4 gap-y-3">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-tinta-700 hover:text-tinta-950 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tinta-500" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-button x-bind:disabled="enviando" class="disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="! enviando">{{ __('Log in') }}</span>
                    <span x-show="enviando" x-cloak>Ingresando…</span>
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
