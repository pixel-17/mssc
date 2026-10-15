<x-form-section id="informacion" submit="updateProfileInformation">
    <x-slot name="title">{{ __('Profile Information') }}</x-slot>
    <x-slot name="description">{{ __('Update your account\'s profile information and email address.') }}</x-slot>

    <x-slot name="form">
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6">
                <input type="file" id="photo" class="hidden"
                       wire:model.live="photo"
                       x-ref="photo"
                       x-on:change="
                           photoName = $refs.photo.files[0].name;
                           const reader = new FileReader();
                           reader.onload = (e) => { photoPreview = e.target.result; };
                           reader.readAsDataURL($refs.photo.files[0]);
                       " />

                <x-label for="photo" value="{{ __('Photo') }}" />

                <div class="mt-2 flex flex-wrap items-center gap-4">
                    {{-- Foto actual / vista previa de la nueva --}}
                    <img x-show="! photoPreview" src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}"
                         width="80" height="80" style="width: 80px; height: 80px; max-width: none;"
                         class="size-20 rounded-full object-cover ring-4 ring-tinta-50 dark:ring-white/10">
                    <span x-show="photoPreview" style="display: none;"
                          class="block size-20 rounded-full bg-cover bg-center bg-no-repeat ring-4 ring-tinta-50 dark:ring-white/10"
                          x-bind:style="'width: 80px; height: 80px; background-image: url(\'' + photoPreview + '\');'"></span>

                    <div class="flex flex-wrap gap-2">
                        <x-secondary-button type="button" x-on:click.prevent="$refs.photo.click()">
                            {{ __('Select A New Photo') }}
                        </x-secondary-button>

                        @if ($this->user->profile_photo_path)
                            <x-secondary-button type="button" wire:click="deleteProfilePhoto">
                                {{ __('Remove Photo') }}
                            </x-secondary-button>
                        @endif
                    </div>
                </div>

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        {{-- Nombre: solo lectura, lo gestiona el administrador --}}
        <x-admin.campo class="col-span-6 md:col-span-3" for="name" :label="__('Name')" campo="state.name"
                       ayuda="Si necesitas corregir tu nombre, pídeselo a un administrador.">
            <x-input id="name" type="text" class="block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-800" wire:model="state.name" disabled readonly autocomplete="name" />
        </x-admin.campo>

        <div class="col-span-6 md:col-span-3">
            <x-admin.campo for="email" :label="__('Email')" campo="state.email">
                <x-input id="email" type="email" class="block w-full" wire:model="state.email" required autocomplete="username" />
            </x-admin.campo>

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <x-texto-seccion class="mt-2">
                    {{ __('Your email address is unverified.') }}

                    <button type="button" class="underline rounded-md hover:text-tinta-950 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tinta-500 dark:hover:text-white" wire:click.prevent="sendEmailVerification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </x-texto-seccion>

                @if ($this->verificationLinkSent)
                    <p class="mt-2 text-sm font-medium text-registro-600 dark:text-registro-100">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">{{ __('Save') }}</x-button>
    </x-slot>
</x-form-section>
