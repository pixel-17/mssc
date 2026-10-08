/**
 * Confirmación propia en lugar del confirm() nativo del navegador
 * ("127.0.0.1:8000 dice…").
 *
 * window.mssConfirmar(mensaje, { titulo, aceptar, cancelar, peligro })
 * devuelve una Promise<boolean>. El modal lo pinta
 * layouts/partials/confirmar.blade.php; si una página no lo incluye, se cae
 * al confirm() nativo para no romper nada.
 */
window.mssConfirmar = (mensaje) => Promise.resolve(window.confirm(mensaje));

// wire:confirm de Livewire 3 llama a confirm() de forma síncrona; se
// reemplaza su comportamiento para que use el modal.
document.addEventListener('livewire:init', () => {
    window.Livewire.directive('confirm', ({ el, directive }) => {
        const mensaje = (directive.expression || '').replaceAll('\\n', '\n') || '¿Estás seguro?';
        const peligro = /eliminar|quitar|deshacer|desactivar|cancelar/i.test(mensaje);

        el.__livewire_confirm = (accion, en_su_lugar = () => {}) => {
            window.mssConfirmar(mensaje, { peligro }).then((ok) => (ok ? accion() : en_su_lugar()));
        };
    });
});
