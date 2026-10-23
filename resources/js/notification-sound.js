/**
 * Sonido de notificación en tiempo real ("whoosh + tap", afinado).
 *
 * EscuchaNotificacionesEnVivo (backend) dispara el evento de
 * navegador 'notificacion-sonido' cada vez que a este usuario le
 * llega una PapeletaNotification por el canal privado de Reverb
 * (nueva papeleta, decisión de jefe/RRHH, etc.). Este archivo solo
 * escucha ese evento y reproduce el tono — no sabe nada de papeletas
 * ni de canales, así que sirve igual para la campana que para
 * cualquier bandeja (Trabajador, Jefe, RRHH, Dashboard).
 *
 * El tono se genera con Web Audio API (nada de un archivo .mp3 que
 * cargar): un barrido ascendente C4→C5 (261.63→523.25 Hz) seguido de
 * un "tap" en C6 (1046.5 Hz). Las tres frecuencias son la misma nota
 * en distintas octavas a propósito — así no queda desafinado como la
 * primera versión (que saltaba de 900 Hz a 1568 Hz, un intervalo que
 * no es limpio).
 *
 * Volumen: única fuente de verdad es `window.MSSC_VOLUMEN_NOTIFICACION`
 * (0-100), impreso por layouts/app.blade.php desde users.volumen_notificacion
 * (ver DeviceSettingsForm). El botón "Probar sonido" de Ajustes llama a
 * reproducirSonidoNotificacion(volumen) directamente con el valor que el
 * usuario está arrastrando en el slider, así que escucha exactamente el
 * mismo sonido que sonará en una notificación real.
 */

let contextoAudio = null;

function obtenerContexto() {
    if (!contextoAudio) {
        const AudioContextCtor = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextCtor) {
            return null;
        }

        contextoAudio = new AudioContextCtor();
    }

    return contextoAudio;
}

/**
 * Oscilador + envolvente de ganancia. `volumenPico` ya viene escalado
 * por el volumen del usuario (0-1) desde reproducirSonidoNotificacion.
 */
function tono(ctx, salida, momento, frecuencia, duracion, tipo, volumenPico) {
    const oscilador = ctx.createOscillator();
    const ganancia = ctx.createGain();

    oscilador.type = tipo;
    oscilador.frequency.setValueAtTime(frecuencia, momento);

    // Ataque corto (2ms) en vez de lineal a 10ms: un flanco más vertical
    // se percibe como "clic" nítido en vez de "swell" difuso.
    ganancia.gain.setValueAtTime(0, momento);
    ganancia.gain.linearRampToValueAtTime(volumenPico, momento + 0.002);
    ganancia.gain.exponentialRampToValueAtTime(0.0001, momento + duracion);

    oscilador.connect(ganancia).connect(salida);
    oscilador.start(momento);
    oscilador.stop(momento + duracion + 0.02);

    return ganancia;
}

/**
 * @param {number} volumenPorcentaje 0-100. Si se omite, usa
 *   window.MSSC_VOLUMEN_NOTIFICACION (o 80 si tampoco está disponible).
 */
export function reproducirSonidoNotificacion(volumenPorcentaje) {
    const ctx = obtenerContexto();

    if (!ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        ctx.resume();
    }

    const porcentaje = volumenPorcentaje ?? window.MSSC_VOLUMEN_NOTIFICACION ?? 80;
    const factor = Math.max(0, Math.min(100, Number(porcentaje))) / 100;

    if (factor === 0) {
        return;
    }

    // Un leve filtro pasa-altos deja fuera el "cuerpo" grave del sine
    // wave, que es lo que hacía sonar el tono apagado/sordo en vez de
    // nítido. No afecta las frecuencias del tono (261-1046 Hz) salvo
    // quitarles redondez en los bordes bajos.
    const filtro = ctx.createBiquadFilter();
    filtro.type = 'highpass';
    filtro.frequency.value = 180;
    filtro.connect(ctx.destination);

    const inicio = ctx.currentTime;

    // Whoosh: barrido C4 -> C5.
    const oscilador = ctx.createOscillator();
    const ganancia = ctx.createGain();
    oscilador.type = 'sine';
    oscilador.frequency.setValueAtTime(261.63, inicio);
    oscilador.frequency.exponentialRampToValueAtTime(523.25, inicio + 0.15);
    ganancia.gain.setValueAtTime(0.001, inicio);
    ganancia.gain.linearRampToValueAtTime(0.22 * factor, inicio + 0.03);
    ganancia.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.18);
    oscilador.connect(ganancia).connect(filtro);
    oscilador.start(inicio);
    oscilador.stop(inicio + 0.2);

    // Tap: C6, una octava arriba del final del barrido. 'triangle' en
    // vez de 'sine' agrega armónicos impares altos — es lo que hace que
    // un "tap" se perciba nítido/seco en vez de redondo/apagado.
    tono(ctx, filtro, inicio + 0.18, 1046.5, 0.15, 'triangle', 0.3 * factor);
}

// Expuesta en window (además del export del módulo) para que
// device-settings.js la use desde el botón "Probar sonido" sin
// necesitar un import dinámico — ambos archivos ya se cargan juntos
// como parte del bundle de resources/js/app.js.
window.msscReproducirSonidoNotificacion = reproducirSonidoNotificacion;

document.addEventListener('livewire:init', () => {
    Livewire.on('notificacion-sonido', () => {
        reproducirSonidoNotificacion();
    });
});