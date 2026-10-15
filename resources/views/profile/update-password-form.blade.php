<x-form-section id="contrasena" submit="updatePassword">
    <x-slot name="title">{{ __('Update Password') }}</x-slot>
    <x-slot name="description">{{ __('Ensure your account is using a long, random password to stay secure.') }}</x-slot>

    <x-slot name="form">
        <x-admin.campo class="col-span-6 md:col-span-4" for="current_password" :label="__('Current Password')" campo="state.current_password">
            <x-input id="current_password" type="password" class="block w-full" wire:model="state.current_password" autocomplete="current-password" />
        </x-admin.campo>

        <x-admin.campo class="col-span-6 md:col-span-4" for="password" :label="__('New Password')" campo="state.password">
            <x-input id="password" type="password" class="block w-full" wire:model="state.password" autocomplete="new-password" />
        </x-admin.campo>

        <x-admin.campo class="col-span-6 md:col-span-4" for="password_confirmation" :label="__('Confirm Password')" campo="state.password_confirmation">
            <x-input id="password_confirmation" type="password" class="block w-full" wire:model="state.password_confirmation" autocomplete="new-password" />
        </x-admin.campo>
    </x-slot>

    <x-slot name="actions">
        <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>
        <x-button>{{ __('Save') }}</x-button>
    </x-slot>
</x-form-section>
