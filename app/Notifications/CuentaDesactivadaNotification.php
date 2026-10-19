<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se envía al correo del usuario cuando intenta autenticarse con
 * credenciales correctas pero su cuenta está desactivada (activo = false).
 *
 * Solo usa el canal 'mail': no tiene sentido guardar en base de datos ni
 * hacer push a alguien que no puede entrar al sistema.
 *
 * ShouldQueue: el envío de email nunca debe bloquear el request de login.
 * Requiere un worker corriendo (php artisan queue:work).
 */
class CuentaDesactivadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return int[] */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu cuenta ha sido desactivada')
            ->greeting("Hola, {$notifiable->name}.")
            ->line('Alguien intentó iniciar sesión con tus credenciales, pero tu cuenta ha sido desactivada.')
            ->line('Si crees que esto es un error o necesitás recuperar el acceso, comunícate con tu jefe inmediato o con el área de RR. HH.')
            ->salutation('Saludos, el equipo de sistemas.');
    }
}
