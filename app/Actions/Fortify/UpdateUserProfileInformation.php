<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        // 'name' queda fuera a propósito: el usuario no debe poder cambiar
        // su propio nombre (lo gestiona el administrador desde
        // UsuarioAdminForm). No se valida ni se escribe, aunque venga en
        // el request — así no se puede sortear editando el payload a mano.
        Validator::make($input, [
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (($input['photo'] ?? null) instanceof UploadedFile) {
            $user->updateProfilePhoto($input['photo']);
        }

        $email = is_string($input['email']) ? $input['email'] : '';

        if ($email !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $email);
        } else {
            $user->forceFill([
                'email' => $email,
            ])->save();
        }
    }

    /** Update the given verified user's profile information. */
    protected function updateVerifiedUser(User $user, string $email): void
    {
        $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
