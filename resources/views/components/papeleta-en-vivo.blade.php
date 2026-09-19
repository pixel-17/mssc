@props(['papeleta'])

{{--
    Detalle de papeleta en tiempo real. Las bandejas (Livewire) ya se
    refrescan solas; el detalle es una vista Blade normal, así que este
    componente escucha PapeletaActualizada por Reverb y, si es de ESTA
    papeleta, vuelve a pedir la misma página y reemplaza solo dos
    zonas marcadas:

      [data-en-vivo-estado]     la insignia del estado (con un destello)
      [data-en-vivo-contenido]  el cuerpo: datos, acciones, historial

    Si la persona está escribiendo (un comentario, un archivo elegido)
    no se le pisa el formulario: aparece un aviso con "Actualizar".
    Colócalo FUERA de [data-en-vivo-contenido] para que no se reemplace
    a sí mismo.
--}}

@auth
    <div
        x-data="{
            avisar: false,
            espera: null,

            init() {
                if (! window.Echo) {
                    return;
                }

                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                    .listen('PapeletaActualizada', (e) => {
                        if (Number(e.papeleta_id) !== {{ $papeleta->id }}) {
                            return;
                        }

                        clearTimeout(this.espera);
                        this.espera = setTimeout(() => this.actualizar(false), 400);
                    });
            },

            escribiendo() {
                const zona = document.querySelector('[data-en-vivo-contenido]');

                if (! zona) {
                    return false;
                }

                const activo = document.activeElement;

                if (activo && zona.contains(activo) && activo.matches('input, textarea, select')) {
                    return true;
                }

                return [...zona.querySelectorAll('textarea, input[type=text], input[type=file]')]
                    .some((el) => el.type === 'file' ? el.files.length > 0 : el.value.trim() !== '');
            },

            async actualizar(forzar) {
                if (! forzar && this.escribiendo()) {
                    this.avisar = true;

                    return;
                }

                try {
                    const r = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        credentials: 'same-origin',
                    });

                    if (! r.ok) {
                        return;
                    }

                    const doc = new DOMParser().parseFromString(await r.text(), 'text/html');

                    const contenidoNuevo = doc.querySelector('[data-en-vivo-contenido]');
                    const contenido = document.querySelector('[data-en-vivo-contenido]');

                    if (! contenidoNuevo || ! contenido) {
                        return;
                    }

                    contenido.innerHTML = contenidoNuevo.innerHTML;

                    const estadoNuevo = doc.querySelector('[data-en-vivo-estado]');
                    const estado = document.querySelector('[data-en-vivo-estado]');

                    if (estadoNuevo && estado) {
                        estado.innerHTML = estadoNuevo.innerHTML;
                        estado.classList.add('animate-pulse');
                        setTimeout(() => estado.classList.remove('animate-pulse'), 1800);
                    }

                    this.avisar = false;
                } catch (error) {
                    // Sin red o sesión vencida: se queda lo que ya se ve.
                }
            },
        }"
    >
        <div
            x-show="avisar"
            x-cloak
            class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-azul-300 bg-azul-50 px-4 py-3 text-sm text-azul-800 dark:border-azul-400/30 dark:bg-azul-500/10 dark:text-azul-200"
            role="status"
        >
            <span>Esta papeleta cambió de estado mientras escribías.</span>
            <button type="button" class="font-semibold underline" @click="actualizar(true)">Actualizar</button>
        </div>
    </div>
@endauth
