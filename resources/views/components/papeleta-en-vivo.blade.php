{{--
    Refresca SOLO el contenido de [data-papeleta-id] cuando llega
    App\Events\PapeletaActualizada por el canal privado del usuario —
    no recarga la pestaña entera.

    Por qué no basta con notification-toast: ese escucha .notification()
    (notificaciones de BD), que no se envían en todos los cambios de
    estado (ver el docblock de PapeletaActualizada). Este escucha el
    broadcast que SÍ cubre todo cambio.

    Cómo actualiza: pide la misma URL por fetch y reemplaza el innerHTML
    de TODOS los nodos [data-papeleta-id="X"] que haya en la página —
    no solo uno. Hace falta porque en jefe/rrhh el badge de estado vive
    en el <x-slot name="header"> del layout (fuera del contenedor
    principal), así que ese nodo también lleva el mismo marcador.
    Empareja por orden de aparición: si el número de nodos marcados no
    coincide entre lo que hay en pantalla y lo que llegó por fetch, no
    toca nada (evita mezclar contenido de layouts distintos). Livewire
    ya expone window.Alpine, así que reinicializamos Alpine en cada nodo
    para que botones/forms dentro sigan funcionando (x-data, @click).

    No reemplaza si el foco está en un input/textarea dentro del
    contenedor (p. ej. el trabajador escribiendo su respuesta a una
    observación): evita borrarle lo que está escribiendo. El próximo
    evento que llegue sí se aplicará si ya no hay foco ahí.

    Uso: colocar junto al contenedor marcado con data-en-vivo-contenido
    data-papeleta-id="...".
--}}
@props(['papeletaId'])

@auth
    <div
        x-data="{
            papeletaId: {{ (int) $papeletaId }},
            actualizando: false,

            init() {
                if (! window.Echo) {
                    return;
                }

                // Sin leading-dot, Echo le antepondría el namespace por defecto
                // (App.Events) y lo convertiría a puntos; con el dot se usa tal
                // cual el nombre que Laravel manda por defecto (clase completa
                // con backslashes), que es el mismo que ya escuchan
                // EscuchaNotificacionesEnVivo e InsigniaBandeja — no se le puso
                // broadcastAs() a propósito, para no duplicar el nombre en dos
                // formatos distintos.
                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                    .listen('.App\\Events\\PapeletaActualizada', (e) => {
                        if (e.papeleta_id === this.papeletaId) {
                            this.actualizarContenido();
                        }
                    });
            },

            selectorContenedor() {
                return `[data-papeleta-id='${this.papeletaId}']`;
            },

            async actualizarContenido() {
                const actuales = Array.from(document.querySelectorAll(this.selectorContenedor()));
                if (actuales.length === 0 || this.actualizando) {
                    return;
                }

                // No pisar lo que el trabajador/jefe está escribiendo ahora mismo
                // en ningún nodo marcado (textarea de respuesta, etc.).
                const hayFocoActivo = actuales.some((nodo) => document.activeElement
                    && nodo.contains(document.activeElement)
                    && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName));

                if (hayFocoActivo) {
                    return;
                }

                this.actualizando = true;

                try {
                    const resp = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store',
                    });

                    if (! resp.ok) {
                        return;
                    }

                    const html = await resp.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const nuevos = Array.from(doc.querySelectorAll(this.selectorContenedor()));

                    // Mismo marcador, distinta cantidad de nodos: no se sabe
                    // emparejarlos uno a uno, mejor no tocar nada.
                    if (nuevos.length !== actuales.length) {
                        return;
                    }

                    actuales.forEach((destino, i) => {
                        destino.innerHTML = nuevos[i].innerHTML;

                        if (window.Alpine) {
                            window.Alpine.initTree(destino);
                        }
                    });
                } catch (e) {
                    // Sin conexión o Reverb/red con problemas: se queda con lo que había
                    // en pantalla y lo intenta de nuevo en el próximo evento.
                } finally {
                    this.actualizando = false;
                }
            },
        }"
        class="hidden"
        aria-hidden="true"
    ></div>
@endauth
