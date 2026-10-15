<x-action-section id="seguridad">
    <x-slot name="title">{{ __('Two Factor Authentication') }}</x-slot>
    <x-slot name="description">{{ __('Add additional security to your account using two factor authentication.') }}</x-slot>

    <x-slot name="content">
        <div class="flex flex-wrap items-center gap-3">
            <h4 class="text-base font-semibold text-tinta-950 dark:text-white">
                @if ($this->enabled)
                    {{ $showingConfirmation ? __('Finish enabling two factor authentication.') : __('You have enabled two factor authentication.') }}
                @else
                    {{ __('You have not enabled two factor authentication.') }}
                @endif
            </h4>

            @if ($this->enabled && ! $showingConfirmation)
                <x-etiqueta tono="registro" punto>Activa</x-etiqueta>
            @endif
        </div>

        <x-texto-seccion class="mt-3">
            <p>{{ __('When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone\'s Google Authenticator application.') }}</p>
        </x-texto-seccion>

        @if ($this->enabled)
            @if ($showingQrCode)
                <x-texto-seccion class="mt-5 font-semibold">
                    @if ($showingConfirmation)
                        {{ __('To finish enabling two factor authentication, scan the following QR code using your phone\'s authenticator application or enter the setup key and provide the generated OTP code.') }}
                    @else
                        {{ __('Two factor authentication is now enabled. Scan the following QR code using your phone\'s authenticator application or enter the setup key.') }}
                    @endif
                </x-texto-seccion>

                <div class="mt-4 inline-block rounded-xl border border-tinta-100 bg-white p-3 dark:border-white/10">
                    {!! $this->user->twoFactorQrCodeSvg() !!}
                </div>

                <x-texto-seccion class="mt-4 font-semibold">
                    {{ __('Setup Key') }}: <span class="font-mono">{{ decrypt($this->user->two_factor_secret) }}</span>
                </x-texto-seccion>

                @if ($showingConfirmation)
                    <x-admin.campo class="mt-4 max-w-xs" for="code" :label="__('Code')" campo="code">
                        <x-input id="code" type="text" name="code" class="block w-full" inputmode="numeric" autofocus autocomplete="one-time-code"
                                 wire:model="code"
                                 wire:keydown.enter="confirmTwoFactorAuthentication" />
                    </x-admin.campo>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <x-texto-seccion class="mt-5 font-semibold">
                    {{ __('Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost.') }}
                </x-texto-seccion>

                <x-codigos class="mt-4" :codigos="json_decode(decrypt($this->user->two_factor_recovery_codes), true)" />
            @endif
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            @if (! $this->enabled)
                <x-confirms-password wire:then="enableTwoFactorAuthentication">
                    <x-button type="button" wire:loading.attr="disabled">{{ __('Enable') }}</x-button>
                </x-confirms-password>
            @else
                @if ($showingRecoveryCodes)
                    <x-confirms-password wire:then="regenerateRecoveryCodes">
                        <x-secondary-button>{{ __('Regenerate Recovery Codes') }}</x-secondary-button>
                    </x-confirms-password>
                @elseif ($showingConfirmation)
                    <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                        <x-button type="button" wire:loading.attr="disabled">{{ __('Confirm') }}</x-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="showRecoveryCodes">
                        <x-secondary-button>{{ __('Show Recovery Codes') }}</x-secondary-button>
                    </x-confirms-password>
                @endif

                @if ($showingConfirmation)
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-secondary-button wire:loading.attr="disabled">{{ __('Cancel') }}</x-secondary-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-danger-button wire:loading.attr="disabled">{{ __('Disable') }}</x-danger-button>
                    </x-confirms-password>
                @endif
            @endif
        </div>
    </x-slot>
</x-action-section>
