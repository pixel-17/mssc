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

function tono(ctx, momento, frecuencia, duracion, tipo = 'sine', volumenPico = 0.25) {
    const oscilador = ctx.createOscillator();
    const ganancia = ctx.createGain();

    oscilador.type = tipo;
    oscilador.frequency.setValueAtTime(frecuencia, momento);

    ganancia.gain.setValueAtTime(0, momento);
    ganancia.gain.linearRampToValueAtTime(volumenPico, momento + 0.01);
    ganancia.gain.exponentialRampToValueAtTime(0.0001, momento + duracion);

    oscilador.connect(ganancia).connect(ctx.destination);
    oscilador.start(momento);
    oscilador.stop(momento + duracion + 0.02);
}

export function reproducirSonidoNotificacion() {
    const ctx = obtenerContexto();

    if (!ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        ctx.resume();
    }

    const inicio = ctx.currentTime;

    // Whoosh: barrido C4 -> C5.
    const oscilador = ctx.createOscillator();
    const ganancia = ctx.createGain();
    oscilador.type = 'sine';
    oscilador.frequency.setValueAtTime(261.63, inicio);
    oscilador.frequency.exponentialRampToValueAtTime(523.25, inicio + 0.15);
    ganancia.gain.setValueAtTime(0.001, inicio);
    ganancia.gain.linearRampToValueAtTime(0.18, inicio + 0.05);
    ganancia.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.18);
    oscilador.connect(ganancia).connect(ctx.destination);
    oscilador.start(inicio);
    oscilador.stop(inicio + 0.2);

    // Tap: C6, una octava arriba del final del barrido.
    tono(ctx, inicio + 0.18, 1046.5, 0.15, 'sine', 0.2);
}

document.addEventListener('livewire:init', () => {
    Livewire.on('notificacion-sonido', () => {
        reproducirSonidoNotificacion();
    });
});
