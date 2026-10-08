{{--
    Vista previa de adjuntos (PDF o foto) sin salir de la papeleta.
    - Celular (incluida la app instalada): tarjeta centrada sobre la página, con botones
      "Cerrar" y "Descargar" abajo, siempre visibles y fuera de la barra de estado.
    - Escritorio: panel en la mitad derecha; la página se achica a la izquierda (no se superpone).
    Se cierra con "Cerrar", con la X, con Esc o tocando el fondo (en celular).
    Sin Alpine: JS puro. Si el navegador no ejecuta JS, el enlace sigue funcionando.
--}}
<div id="visor-archivo" class="fixed inset-0 z-[70] hidden pointer-events-none" aria-hidden="true">
    <div id="visor-archivo-fondo" class="absolute inset-0 bg-black/40 pointer-events-auto sm:pointer-events-none sm:bg-transparent"></div>

    <aside id="visor-archivo-panel"
           role="dialog" aria-modal="false" aria-labelledby="visor-archivo-titulo"
           class="pointer-events-auto absolute inset-x-3 top-[max(env(safe-area-inset-top),0.75rem)] bottom-[max(env(safe-area-inset-bottom),0.75rem)] flex flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl border border-gray-200 dark:border-white/10 sm:inset-x-auto sm:inset-y-0 sm:right-0 sm:top-0 sm:bottom-0 sm:w-1/2 sm:rounded-none sm:border-y-0 sm:border-r-0">
        <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 dark:border-white/10 shrink-0">
            <p id="visor-archivo-titulo" class="flex-1 min-w-0 text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">Archivo adjunto</p>
            <button type="button" id="visor-archivo-x" aria-label="Cerrar vista previa" class="inline-flex items-center justify-center size-9 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </header>

        <div class="flex-1 min-h-0 bg-gray-100 dark:bg-zinc-800">
            <iframe id="visor-archivo-frame" title="Vista previa del archivo" class="w-full h-full border-0" src="about:blank"></iframe>
        </div>

        <footer class="grid grid-cols-2 gap-2 p-3 border-t border-gray-200 dark:border-white/10 shrink-0">
            <button type="button" id="visor-archivo-volver" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-gray-100 dark:bg-white/10 px-4 py-3 text-sm font-semibold text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-white/15">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                Cerrar
            </button>
            <a id="visor-archivo-descargar" href="#" download class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-tinta-700 px-4 py-3 text-sm font-semibold text-white hover:bg-tinta-800">
                Descargar
            </a>
        </footer>
    </aside>
</div>

<script>
    (() => {
        const visor = document.getElementById('visor-archivo');
        if (!visor) return;
        const frame = document.getElementById('visor-archivo-frame');
        const titulo = document.getElementById('visor-archivo-titulo');
        const botonVolver = document.getElementById('visor-archivo-volver');
        const botonX = document.getElementById('visor-archivo-x');
        const botonDescargar = document.getElementById('visor-archivo-descargar');
        const fondo = document.getElementById('visor-archivo-fondo');

        // En escritorio la página se achica a la izquierda para dejar la mitad derecha al archivo.
        const contenido = document.getElementById('contenido');
        const esEscritorio = window.matchMedia('(min-width: 640px)');

        const reducirPagina = (activo) => {
            if (!contenido) return;
            contenido.style.transition = 'margin-right 250ms ease';
            contenido.style.marginRight = (activo && esEscritorio.matches) ? '50%' : '';
        };

        const abrir = (href, texto) => {
            titulo.textContent = texto || 'Archivo adjunto';
            frame.src = href;
            botonDescargar.href = href;
            visor.classList.remove('hidden');
            visor.setAttribute('aria-hidden', 'false');
            reducirPagina(true);
            botonVolver.focus();
        };

        const cerrar = () => {
            visor.classList.add('hidden');
            visor.setAttribute('aria-hidden', 'true');
            frame.src = 'about:blank';
            reducirPagina(false);
        };

        botonVolver.addEventListener('click', cerrar);
        botonX.addEventListener('click', cerrar);
        fondo.addEventListener('click', cerrar);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !visor.classList.contains('hidden')) cerrar(); });

        document.addEventListener('click', (e) => {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            const enlace = e.target.closest('a[href]');
            if (!enlace) return;
            const href = enlace.getAttribute('href');
            if (!href || !/\/archivo(\?|$)/.test(href)) return;
            e.preventDefault();
            abrir(enlace.href, enlace.textContent.trim());
        });
    })();
</script>