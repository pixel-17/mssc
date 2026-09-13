/**
 * Estado global del sidebar (Alpine.store), compartido entre el
 * componente Livewire de navegación y el layout que reserva el
 * espacio a la izquierda del contenido.
 *
 * - collapsed: sidebar en modo solo-iconos en escritorio (persiste).
 * - mobileOpen: drawer visible en móvil (no persiste, siempre arranca cerrado).
 */
const CLAVE = 'mssc-sidebar-collapsed';

document.addEventListener('alpine:init', () => {
    Alpine.store('sidebar', {
        collapsed: localStorage.getItem(CLAVE) === '1',
        mobileOpen: false,

        toggleCollapse() {
            this.collapsed = !this.collapsed;
            localStorage.setItem(CLAVE, this.collapsed ? '1' : '0');
        },

        openMobile() {
            this.mobileOpen = true;
        },

        closeMobile() {
            this.mobileOpen = false;
        },
    });
});
