<div>
    <div class="max-w-md mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h2 class="font-bold text-2xl text-ocean-950 leading-tight tracking-tight">
                Actualiza tu contraseña
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Tu contraseña actual es tu DNI. Por seguridad, te recomendamos cambiarla ahora —
                aunque puedes omitir este paso y hacerlo después desde tu perfil.
            </p>
        </div>

        <form wire:submit="actualizar" class="glass-card p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Nueva contraseña</label>
                <input type="password" wire:model="password" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
                @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Confirmar nueva contraseña</label>
                <input type="password" wire:model="password_confirmation" class="w-full rounded-md border-gray-300 dark:bg-gray-800">
            </div>

            <div class="flex items-center justify-between pt-2">
                <button
                    type="button"
                    wire:click="omitir"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                >
                    Omitir por ahora
                </button>

                <button type="submit" class="px-4 py-2 rounded-md bg-ocean-600 text-white text-sm font-semibold hover:bg-ocean-700">
                    Actualizar contraseña
                </button>
            </div>
        </form>
    </div>
</div>
