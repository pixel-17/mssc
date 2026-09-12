/**
 * Preferencias del dispositivo (resources/views/profile/device-settings-form.blade.php).
 *
 * Importante: el navegador NUNCA deja "activar/desactivar" GPS o
 * cámara por código. Solo se puede:
 *   1) pedir el permiso (abre el diálogo nativo una vez), y
 *   2) leer si quedó 'granted' | 'denied' | 'prompt'.
 * Si el usuario lo revoca, solo lo reactiva desde el candadito del
 * navegador — por eso el botón "solicitar permiso" se oculta cuando
 * ya está concedido, pero no hay forma de "apagarlo" desde aquí.
 */

window.msscGpsToggle = function () {
    return {
        estado: 'Consultando permiso…',

        async init() {
            if (!('geolocation' in navigator)) {
                this.estado = 'Este navegador no soporta ubicación.';
                return;
            }

            await this.consultar();
        },

        async consultar() {
            if (!('permissions' in navigator)) {
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
                return;
            }

            try {
                const resultado = await navigator.permissions.query({ name: 'geolocation' });
                this.actualizarEstado(resultado.state);
                resultado.onchange = () => this.actualizarEstado(resultado.state);
            } catch (error) {
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
            }
        },

        actualizarEstado(state) {
            const etiquetas = {
                granted: 'Permiso concedido',
                denied: 'Permiso denegado (actívalo desde los ajustes del navegador)',
                prompt: 'Aún no se ha pedido el permiso',
            };

            this.estado = etiquetas[state] || state;
        },

        pedirPermiso() {
            navigator.geolocation.getCurrentPosition(
                () => this.consultar(),
                () => this.consultar()
            );
        },
    };
};

window.msscCamaraToggle = function () {
    return {
        estado: 'Consultando permiso…',

        async init() {
            if (!('mediaDevices' in navigator)) {
                this.estado = 'Este navegador no soporta cámara.';
                return;
            }

            await this.consultar();
        },

        async consultar() {
            if (!('permissions' in navigator)) {
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
                return;
            }

            try {
                const resultado = await navigator.permissions.query({ name: 'camera' });
                this.actualizarEstado(resultado.state);
                resultado.onchange = () => this.actualizarEstado(resultado.state);
            } catch (error) {
                // Algunos navegadores (ej. Firefox) no exponen 'camera' en permissions.query.
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
            }
        },

        actualizarEstado(state) {
            const etiquetas = {
                granted: 'Permiso concedido',
                denied: 'Permiso denegado (actívalo desde los ajustes del navegador)',
                prompt: 'Aún no se ha pedido el permiso',
            };

            this.estado = etiquetas[state] || state;
        },

        async pedirPermiso() {
            try {
                const flujo = await navigator.mediaDevices.getUserMedia({ video: true });
                flujo.getTracks().forEach((pista) => pista.stop());
            } catch (error) {
                // Denegado o sin cámara; el estado se actualiza igual.
            }

            await this.consultar();
        },
    };
};

/**
 * Volumen del sonido de notificación in-app. Se guarda en el
 * servidor (users.volumen_notificacion vía DeviceSettingsForm), pero
 * se reproduce localmente aquí. Reutiliza este mismo helper
 * (window.msscReproducirSonidoNotificacion) donde el proyecto termine
 * de enganchar el sonido a una notificación real recibida.
 */
window.msscReproducirSonidoNotificacion = function (volumenPorcentaje) {
    const audio = new Audio('/sounds/notificacion.mp3');
    audio.volume = Math.max(0, Math.min(100, volumenPorcentaje)) / 100;
    audio.play().catch(() => {});
};

window.msscVolumenNotificacion = function (volumenInicial) {
    return {
        volumen: volumenInicial,

        init() {
            this.$watch('volumen', (valor) => {
                this.volumen = Number(valor);
            });

            // El componente Livewire avisa cuando guarda, por si otra
            // pestaña/parte de la UI necesita el valor actualizado.
            this.$wire.on('mssc-volumen-actualizado', ({ volumen }) => {
                this.volumen = volumen;
            });
        },

        probar() {
            window.msscReproducirSonidoNotificacion(this.volumen);
        },
    };
};

/**
 * Botón "Instalar app" (Add to Home Screen). El navegador dispara
 * 'beforeinstallprompt' solo si hay un manifest.json válido (ver
 * public/manifest.json) y la app aún no está instalada; por eso el
 * botón empieza oculto (x-show="instalable") hasta que el evento
 * llega.
 */
let promptDiferido = null;

window.addEventListener('beforeinstallprompt', (evento) => {
    evento.preventDefault();
    promptDiferido = evento;
    window.dispatchEvent(new CustomEvent('mssc-instalable'));
});

window.msscInstalarApp = function () {
    return {
        instalable: false,

        init() {
            window.addEventListener('mssc-instalable', () => {
                this.instalable = true;
            });
        },

        async instalar() {
            if (!promptDiferido) {
                return;
            }

            promptDiferido.prompt();
            await promptDiferido.userChoice;
            promptDiferido = null;
            this.instalable = false;
        },
    };
};
