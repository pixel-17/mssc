/**
 * Web push (notificaciones): registra el Service Worker (public/sw.js)
 * y gestiona la suscripción del navegador contra
 * NotificationChannels\WebPush\HasPushSubscriptions (backend), vía
 * PushSubscriptionController (routes/web.php: POST/DELETE
 * /push-subscriptions).
 *
 * El canal in-app (tabla `notifications`, componente Livewire
 * NotificationBell) sigue siendo el registro de verdad: si el
 * navegador no soporta push, no hay permiso concedido, o el usuario
 * nunca activa el botón, la bandeja in-app sigue funcionando igual.
 */

function base64UrlAUint8Array(base64Url) {
    const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const salida = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; ++i) {
        salida[i] = raw.charCodeAt(i);
    }

    return salida;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function vapidPublicKey() {
    return document.querySelector('meta[name="vapid-public-key"]')?.content ?? '';
}

async function registrarServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    return navigator.serviceWorker.register('/sw.js');
}

async function suscripcionActual() {
    const registro = await registrarServiceWorker();

    return registro ? registro.pushManager.getSubscription() : null;
}

async function suscribir() {
    const clavePublica = vapidPublicKey();

    if (!clavePublica) {
        console.warn('VAPID_PUBLIC_KEY no está configurada; no se puede activar push.');

        return false;
    }

    const permiso = await Notification.requestPermission();

    if (permiso !== 'granted') {
        return false;
    }

    const registro = await registrarServiceWorker();

    const suscripcion = await registro.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: base64UrlAUint8Array(clavePublica),
    });

    await fetch('/push-subscriptions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(suscripcion.toJSON()),
    });

    return true;
}

async function desuscribir() {
    const suscripcion = await suscripcionActual();

    if (!suscripcion) {
        return;
    }

    await fetch('/push-subscriptions', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ endpoint: suscripcion.endpoint }),
    });

    await suscripcion.unsubscribe();
}

/**
 * Componente Alpine usado en navigation-menu.blade.php
 * (x-data="msscPushToggle()"): un único botón que activa o desactiva
 * push según el estado actual de la suscripción del navegador.
 */
window.msscPushToggle = function () {
    return {
        etiqueta: 'Notificaciones push',
        soportado: 'serviceWorker' in navigator && 'PushManager' in window,

        async init() {
            if (!this.soportado) {
                this.etiqueta = 'Push no disponible en este navegador';

                return;
            }

            const suscripcion = await suscripcionActual();
            this.etiqueta = suscripcion ? 'Desactivar notificaciones push' : 'Activar notificaciones push';
        },

        async alternar() {
            if (!this.soportado) {
                return;
            }

            const suscripcion = await suscripcionActual();

            if (suscripcion) {
                await desuscribir();
                this.etiqueta = 'Activar notificaciones push';
            } else {
                const activado = await suscribir();
                this.etiqueta = activado ? 'Desactivar notificaciones push' : 'Activar notificaciones push';
            }
        },
    };
};
