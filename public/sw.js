/**
 * Service worker de web push. Debe vivir en /public (raíz del sitio)
 * para poder controlar el scope completo ('/') — ver el registro en
 * resources/js/push-notifications.js (navigator.serviceWorker.register('/sw.js')).
 *
 * No cachea nada (no es un service worker de "modo offline"), su
 * único trabajo es recibir el payload que manda WebPushChannel
 * (ver App\Notifications\PapeletaNotification::toWebPush) y mostrarlo
 * con showNotification, y llevar al usuario a la URL correspondiente
 * al hacer clic.
 */

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};

    try {
        payload = event.data.json();
    } catch (error) {
        payload = { title: 'Notificación', body: event.data.text() };
    }

    const titulo = payload.title || 'MSSC — Papeletas de salida';
    const opciones = {
        body: payload.body,
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge,
        tag: payload.tag,
        data: payload.data || {},
        actions: payload.actions || [],
        requireInteraction: payload.requireInteraction || false,
    };

    event.waitUntil(self.registration.showNotification(titulo, opciones));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url;

    if (!url) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const cliente of clientList) {
                if (cliente.url === url && 'focus' in cliente) {
                    return cliente.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
