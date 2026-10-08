{{--
    Modal de confirmación global. Reemplaza al confirm() nativo del navegador.
    Uso desde Alpine/JS: mssConfirmar('¿Seguro?', { aceptar: 'Sí', peligro: true }).then(ok => ...)
    Los wire:confirm de Livewire también pasan por aquí (resources/js/confirmar.js).
--}}
<div
    x-data="{
        abierto: false,
        titulo: '',
        mensaje: '',
        aceptar: 'Aceptar',
        cancelar: 'Cancelar',
        peligro: false,
        resolver: null,
        init() {
            window.mssConfirmar = (mensaje, opciones = {}) => new Promise((resolve) => {
                if (this.resolver) { this.resolver(false); }
                this.mensaje = mensaje;
                this.titulo = opciones.titulo ?? '';
                this.aceptar = opciones.aceptar ?? 'Aceptar';
                this.cancelar = opciones.cancelar ?? 'Cancelar';
                this.peligro = !!opciones.peligro;
                this.resolver = resolve;
                this.abierto = true;
                this.$nextTick(() => this.$refs.aceptar?.focus());
            });
        },
        cerrar(valor) {
            if (!this.abierto) return;
            this.abierto = false;
            const r = this.resolver;
            this.resolver = null;
            if (r) r(valor);
        },
    }"
    x-on:keydown.escape.window="cerrar(false)"
>
    <div
        x-show="abierto" x-cloak
        class="fixed inset-0 z-[70] flex items-center justify-center px-4"
        role="alertdialog" aria-modal="true" aria-labelledby="confirmar-mensaje"
    >
        <div
            class="absolute inset-0 bg-gray-900/50"
            x-on:click="cerrar(false)"
            x-transition:enter="ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        ></div>

        <div
            class="relative w-full max-w-sm rounded-xl border border-gray-200 bg-white p-5 shadow-glass-lg dark:border-white/10 dark:bg-zinc-900"
            x-transition:enter="ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        >
            <h3 x-show="titulo" x-text="titulo" class="mb-1 text-base font-semibold text-gray-900 dark:text-white"></h3>
            <p id="confirmar-mensaje" x-text="mensaje" class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-200"></p>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" x-on:click="cerrar(false)" x-text="cancelar" class="btn-secondary justify-center text-sm"></button>
                <button
                    type="button" x-ref="aceptar" x-on:click="cerrar(true)" x-text="aceptar"
                    :class="peligro ? 'btn-danger-glass' : 'btn-primary'"
                    class="justify-center text-sm"
                ></button>
            </div>
        </div>
    </div>
</div>
