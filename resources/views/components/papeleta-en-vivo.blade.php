@props(['papeleta'])

{{--
    Detalle de papeleta en tiempo real. Las bandejas (Livewire) ya se
    refrescan solas; el detalle es una vista Blade normal, así que este
    componente escucha PapeletaActualizada por Reverb y, si es de ESTA
    papeleta, muestra un aviso fijo arriba del todo con el nuevo estado
    y un botón "Actualizar".

    Antes esto reemplazaba el HTML de [data-en-vivo-contenido] por
    fetch + innerHTML en cuanto llegaba el evento (silencioso, o tras
    un aviso si la persona estaba escribiendo). Se quitó porque ese
    reemplazo rompía los `x-data` de Alpine que viven DENTRO de esa
    zona (la captura de foto del retorno, los formularios de
    observación/sustento, etc.): al perder su estado a mitad de uso
    quedaban botones que ya no respondían o formularios vacíos sin que
    la persona entendiera por qué — de ahí la confusión.

    Esta versión no toca el DOM de la página por su cuenta: solo avisa
    (con el estado nuevo, para que sea útil incluso sin recargar) y
    deja que la propia persona decida cuándo recargar con el botón,
    que sí hace una recarga completa (nunca un parche a mitad de
    formulario).
--}}

@auth
    <div
        x-data="{
            visible: false,
            etiqueta: null,

            init() {
                if (! window.Echo) {
                    return;
                }

                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                    .listen('PapeletaActualizada', (e) => {
                        if (Number(e.papeleta_id) !== {{ $papeleta->id }}) {
                            return;
                        }

                        this.etiqueta = e.etiqueta;
                        this.visible = true;
                    });
            },
        }"
        x-show="visible"
        x-cloak
        x-transition
        class="sticky top-0 z-20 mb-4 flex items-center justify-between gap-3 rounded-lg border border-azul-300 bg-azul-50 px-4 py-3 text-sm text-azul-800 shadow-sm dark:border-azul-400/30 dark:bg-azul-500/10 dark:text-azul-200"
        role="status"
    >
        <span>Esta papeleta se actualizó: ahora está <strong x-text="etiqueta"></strong>.</span>
        <button type="button" class="shrink-0 font-semibold underline" @click="window.location.reload()">Actualizar</button>
    </div>
@endauth