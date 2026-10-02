/**
 * Indicador global de carga para Livewire.
 *
 * Casi ninguna vista Livewire tiene wire:loading, así que con conexión
 * lenta (el caso habitual de quien entra desde la calle) un clic parecía
 * no hacer nada. Aquí se cuentan las peticiones en vuelo y se marca <html>
 * con la clase `lw-cargando` (barra superior en app.css) y aria-busy.
 *
 * Solo aparece si la respuesta tarda más de MOSTRAR_TRAS_MS, para que las
 * rápidas no parpadeen. No reemplaza a wire:loading donde ya existe: es el
 * mínimo común para todas las pantallas.
 */
const MOSTRAR_TRAS_MS = 250;

let enVuelo = 0;
let temporizador = null;

function pintar() {
    const raiz = document.documentElement;

    if (enVuelo > 0) {
        if (temporizador === null && ! raiz.classList.contains('lw-cargando')) {
            temporizador = setTimeout(() => {
                temporizador = null;
                raiz.classList.add('lw-cargando');
                raiz.setAttribute('aria-busy', 'true');
            }, MOSTRAR_TRAS_MS);
        }

        return;
    }

    clearTimeout(temporizador);
    temporizador = null;
    raiz.classList.remove('lw-cargando');
    raiz.removeAttribute('aria-busy');
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ respond, succeed, fail }) => {
        enVuelo++;
        pintar();

        // respond/succeed/fail pueden llegar los tres: se cuenta una sola vez.
        let cerrado = false;
        const terminar = () => {
            if (cerrado) return;
            cerrado = true;
            enVuelo = Math.max(0, enVuelo - 1);
            pintar();
        };

        respond(terminar);
        succeed(terminar);
        fail(terminar);
    });
});
