/**
 * Sincroniza la Badging API (el numerito sobre el icono del PWA en
 * escritorio/taskbar, ver public/manifest.json) con la cantidad de
 * notificaciones no leídas.
 *
 * Se actualiza por dos caminos, que se complementan:
 * - public/sw.js lo setea cuando llega un push, incluso con la app
 *   cerrada (ahí no hay Livewire ni este archivo disponible).
 * - Este archivo lo sincroniza mientras la pestaña sigue abierta: al
 *   cargar la página, al marcar como leída desde la campana, o al
 *   llegar una notificación en vivo por Reverb — en todos esos casos
 *   NotificationBell::render() vuelve a correr y dispara el evento
 *   'contador-notificaciones-actualizado' con el número fresco.
 *
 * Sin esto, si el usuario lee las notificaciones adentro de la app
 * (sin recibir un push nuevo), el icono se quedaría con el número
 * viejo hasta el próximo push.
 */

function soportaBadging() {
    return 'setAppBadge' in navigator;
}

export function actualizarBadgeApp(cantidad) {
    if (!soportaBadging()) {
        return;
    }

    const numero = Number(cantidad) || 0;

    if (numero > 0) {
        navigator.setAppBadge(numero).catch(() => {});
    } else {
        navigator.clearAppBadge().catch(() => {});
    }
}

document.addEventListener('livewire:init', () => {
    Livewire.on('contador-notificaciones-actualizado', (cantidad) => {
        // Livewire 3 manda el payload como array de argumentos.
        actualizarBadgeApp(Array.isArray(cantidad) ? cantidad[0] : cantidad);
    });
});