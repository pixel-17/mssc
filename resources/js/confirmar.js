/**
 * Confirmación propia en lugar del confirm() nativo del navegador
 * ("127.0.0.1:8000 dice…").
 *
 * window.mssConfirmar(mensaje, { titulo, aceptar, cancelar, peligro })
 * devuelve una Promise<boolean>. El modal lo pinta
 * layouts/partials/confirmar.blade.php; si una página no lo incluye, se cae
 * al confirm() nativo para no romper nada.
 *
 * Nota: wire:confirm de Livewire 3 NO pasa por aquí ni es personalizable
 * (siempre dispara el confirm() nativo, sin hook documentado para
 * reemplazarlo), así que no se usa en ninguna vista. Las acciones que
 * necesitan confirmación llaman a mssConfirmar() desde x-on:click y, si se
 * acepta, invocan la acción directamente por $wire (ver
 * components/admin/accion-eliminar.blade.php).
 */
window.mssConfirmar = (mensaje) => Promise.resolve(window.confirm(mensaje));