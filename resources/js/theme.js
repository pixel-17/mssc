/**
 * Modo oscuro: automático según el sistema/navegador
 * (prefers-color-scheme) con un botón para forzarlo. La preferencia
 * forzada se guarda en localStorage bajo la clave 'mssc-theme' con
 * los valores 'light' | 'dark'; si no hay nada guardado, el tema
 * sigue al sistema y se actualiza solo si el sistema cambia
 * (ver el listener de matchMedia más abajo).
 *
 * El script que evita el parpadeo (clase `dark` puesta ANTES de que
 * pinte la página) vive inline en layouts/app.blade.php y
 * layouts/guest.blade.php, no aquí: este archivo se carga vía Vite,
 * después de que el HTML ya empezó a pintarse, así que para ese
 * propósito ya sería tarde.
 */

const CLAVE = 'mssc-theme';

function sistemaPrefiereOscuro() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function aplicar(modo) {
    const oscuro = modo === 'dark' || (modo === null && sistemaPrefiereOscuro());
    document.documentElement.classList.toggle('dark', oscuro);
}

/**
 * Componente Alpine usado en navigation-menu.blade.php
 * (x-data="msscThemeToggle()"): un botón que rota entre
 * "automático" -> "claro forzado" -> "oscuro forzado" -> "automático".
 */
window.msscThemeToggle = function () {
    return {
        modo: localStorage.getItem(CLAVE), // 'light' | 'dark' | null (= automático)

        init() {
            aplicar(this.modo);

            // Si el usuario no forzó nada, seguir reflejando el cambio
            // de tema del sistema operativo en caliente, sin recargar.
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.modo === null) {
                    aplicar(null);
                }
            });
        },

        alternar() {
            this.modo = this.modo === null ? 'light' : (this.modo === 'light' ? 'dark' : null);

            if (this.modo === null) {
                localStorage.removeItem(CLAVE);
            } else {
                localStorage.setItem(CLAVE, this.modo);
            }

            aplicar(this.modo);
        },

        get etiqueta() {
            if (this.modo === 'light') {
                return 'Modo claro (forzado) — clic para oscuro';
            }

            if (this.modo === 'dark') {
                return 'Modo oscuro (forzado) — clic para automático';
            }

            return 'Automático (según el sistema) — clic para forzar claro';
        },
    };
};
