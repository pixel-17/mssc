<?php

namespace App\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Sección "Preferencias del dispositivo" del perfil, justo debajo de
 * "Update Password". Solo guarda lo que SÍ vive en el servidor:
 *
 * - volumen_notificacion: usado por resources/js/device-settings.js
 *   para reproducir el sonido de notificación in-app al volumen
 *   elegido (el navegador no permite controlar el volumen de sus
 *   notificaciones nativas).
 * - permite_gps / permite_camara: la INTENCIÓN del usuario. El
 *   permiso real del navegador (concedido/denegado) nunca se guarda
 *   aquí porque no se puede activar por código — eso se pide y se lee
 *   en vivo desde JS (navigator.permissions / getUserMedia).
 *
 * El toggle de notificaciones push en sí (activar/desactivar) sigue
 * viviendo en msscPushToggle() (navigation-menu.blade.php); esta
 * sección solo referencia el volumen para no duplicar esa lógica.
 */
class DeviceSettingsForm extends Component
{
    public int $volumen = 80;

    public bool $permiteGps = false;

    public bool $permiteCamara = false;

    public function mount(): void
    {
        $usuario = Auth::user();

        $this->volumen = $usuario->volumen_notificacion;
        $this->permiteGps = $usuario->permite_gps;
        $this->permiteCamara = $usuario->permite_camara;
    }

    public function guardar(): void
    {
        $usuario = Auth::user();

        $usuario->forceFill([
            'volumen_notificacion' => max(0, min(100, $this->volumen)),
            'permite_gps' => $this->permiteGps,
            'permite_camara' => $this->permiteCamara,
        ])->save();

        $this->dispatch('saved');

        // Avisa a Alpine (device-settings.js) del nuevo volumen para
        // que el botón "probar sonido" y el reproductor real usen el
        // valor recién guardado sin recargar la página.
        $this->dispatch('mssc-volumen-actualizado', volumen: $this->volumen);
    }

    public function render(): View
    {
        return view('profile.device-settings-form');
    }
}
