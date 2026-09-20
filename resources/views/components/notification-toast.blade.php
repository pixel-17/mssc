{{--
    Aviso flotante cuando llega una PapeletaNotification por Reverb.

    Escucha el mismo canal privado que NotificationBell
    (App.Models.User.{id}) y usa el payload de
    PapeletaNotification::toBroadcast() — titulo, mensaje y url. El sonido
    lo sigue manejando resources/js/notification-sound.js por su cuenta;
    aquí solo se pinta.
--}}

@auth
    <div
        x-data="{
            avisos: [],
            siguienteId: 1,

            init() {
                if (! window.Echo) {
                    return;
                }

                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                    .notification((n) => this.mostrar(n));
            },

            mostrar(n) {
                const id = this.siguienteId++;

                this.avisos.push({
                    id,
                    titulo: n.titulo ?? 'Papeletas',
                    mensaje: n.mensaje ?? '',
                    url: n.url ?? null,
                });

                setTimeout(() => this.cerrar(id), 7000);
            },

            cerrar(id) {
                this.avisos = this.avisos.filter((a) => a.id !== id);
            },
        }"
        class="toast-stack"
        role="status"
        aria-live="polite"
    >
        <template x-for="aviso in avisos" :key="aviso.id">
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-end="opacity-0"
                class="toast"
            >
                <span class="toast-marca" aria-hidden="true"></span>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-tinta-950 dark:text-white" x-text="aviso.titulo"></p>
                    <p class="mt-0.5 text-sm text-tinta-700 dark:text-tinta-100/80" x-text="aviso.mensaje"></p>

                    <template x-if="aviso.url">
                        <a :href="aviso.url" class="mt-2 inline-block text-sm font-semibold text-sello-600 hover:text-sello-700 dark:text-sello-300">
                            Ver papeleta
                        </a>
                    </template>
                </div>

                <button
                    type="button"
                    @click="cerrar(aviso.id)"
                    class="icon-btn shrink-0"
                    aria-label="Cerrar aviso"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>
@endauth
