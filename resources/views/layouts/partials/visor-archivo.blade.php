{{--
    Vista previa de adjuntos (PDF o foto) sin tapar la papeleta.
    - Escritorio (>= 1024 px): panel lateral angosto a la derecha con pestañas (un adjunto por
      pestaña). La página se achica a la izquierda y el panel se pliega con la pestañita del borde.
    - Celular: panel inferior a media altura; la papeleta sigue visible y usable arriba.
      Desde el panel se puede ampliar a pantalla completa (o tocar la barrita), minimizar a una
      barra chica o cerrar. No hay fondo oscuro que bloquee la pantalla.
    Se cierra con "Cerrar", con la X o con Esc.
    Sin Alpine: JS puro. Si el navegador no ejecuta JS, el enlace sigue funcionando.
    Los adjuntos que aparecen como pestañas son todos los enlaces ".../archivo" de la página.
--}}
<style>
    #visor-archivo { --va-ancho: clamp(22rem, 32vw, 30rem); --va-asa: 1.25rem; }
    #visor-archivo-panel { pointer-events: auto; position: absolute; display: flex; flex-direction: column; overflow: hidden; }
    #visor-archivo-asa { pointer-events: auto; position: absolute; top: 50%; transform: translateY(-50%); align-items: center; justify-content: center; width: var(--va-asa); height: 3.5rem; border-radius: 0.5rem 0 0 0.5rem; }
    #visor-archivo-chip { pointer-events: auto; position: absolute; left: 0.625rem; right: 0.625rem; align-items: center; gap: 0.5rem; }
    #visor-archivo [data-estado-ico="reducir"] { display: none; }
    #visor-archivo[data-estado="amplio"] [data-estado-ico="reducir"] { display: block; }
    #visor-archivo[data-estado="amplio"] [data-estado-ico="ampliar"] { display: none; }

    @media (max-width: 1023.98px) {
        #visor-archivo-panel { left: 0; right: 0; bottom: 0; height: 55vh; height: 55dvh; border-radius: 1rem 1rem 0 0; padding-bottom: env(safe-area-inset-bottom, 0); transition: height 200ms ease; }
        #visor-archivo[data-estado="amplio"] #visor-archivo-panel { height: calc(100vh - env(safe-area-inset-top, 0px) - 0.5rem); height: calc(100dvh - env(safe-area-inset-top, 0px) - 0.5rem); }
        #visor-archivo[data-estado="minimizado"] #visor-archivo-panel { display: none; }
        #visor-archivo-chip { display: none; }
        #visor-archivo[data-estado="minimizado"] #visor-archivo-chip { display: flex; }
        #visor-archivo-asa, .va-solo-esc { display: none; }
    }
    @media (min-width: 1024px) {
        #visor-archivo-panel { top: 0; bottom: 0; right: 0; width: var(--va-ancho); transition: transform 200ms ease; }
        #visor-archivo[data-estado="plegado"] #visor-archivo-panel { transform: translateX(100%); }
        #visor-archivo-asa { display: flex; right: var(--va-ancho); transition: right 200ms ease; }
        #visor-archivo[data-estado="plegado"] #visor-archivo-asa { right: 0; }
        #visor-archivo[data-estado="abierto"] [data-ico-asa="abrir"], #visor-archivo[data-estado="plegado"] [data-ico-asa="plegar"] { display: none; }
        #visor-archivo-chip, .va-solo-cel { display: none; }
    }
</style>

<div id="visor-archivo" class="fixed inset-0 z-[70] hidden pointer-events-none" data-estado="cerrado" aria-hidden="true">
    <button type="button" id="visor-archivo-asa" aria-label="Plegar o desplegar vista previa"
            class="bg-white dark:bg-zinc-900 border border-r-0 border-gray-200 dark:border-white/10 text-gray-500 hover:text-gray-800 dark:hover:text-gray-100 shadow-md">
        <svg data-ico-asa="plegar" class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
        <svg data-ico-asa="abrir" class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
    </button>

    <aside id="visor-archivo-panel"
           role="dialog" aria-modal="false" aria-labelledby="visor-archivo-titulo"
           class="bg-white dark:bg-zinc-900 shadow-2xl border border-gray-200 dark:border-white/10 lg:border-y-0 lg:border-r-0">
        <button type="button" id="visor-archivo-barra" aria-label="Ampliar o reducir vista previa" class="va-solo-cel flex justify-center pt-2 pb-1 shrink-0">
            <span class="block h-1 w-9 rounded-full bg-gray-300 dark:bg-white/20"></span>
        </button>

        <header class="flex items-center gap-1 px-4 py-2 border-b border-gray-200 dark:border-white/10 shrink-0">
            <p id="visor-archivo-titulo" class="flex-1 min-w-0 text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">Archivo adjunto</p>
            <button type="button" id="visor-archivo-ampliar" aria-label="Ampliar o reducir" class="va-solo-cel inline-flex items-center justify-center size-9 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                <svg data-estado-ico="ampliar" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" /></svg>
                <svg data-estado-ico="reducir" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5m4.5 0l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" /></svg>
            </button>
            <button type="button" id="visor-archivo-minimizar" aria-label="Minimizar vista previa" class="va-solo-cel inline-flex items-center justify-center size-9 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <button type="button" id="visor-archivo-x" aria-label="Cerrar vista previa" class="inline-flex items-center justify-center size-9 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </header>

        <div id="visor-archivo-tabs" role="tablist" aria-label="Archivos adjuntos" class="hidden gap-1.5 overflow-x-auto px-3 py-2 border-b border-gray-200 dark:border-white/10 shrink-0"></div>

        <div class="flex-1 min-h-0 bg-gray-100 dark:bg-zinc-800">
            <iframe id="visor-archivo-frame" title="Vista previa del archivo" class="w-full h-full border-0" src="about:blank"></iframe>
            <div id="visor-archivo-imagen" class="hidden w-full h-full overflow-y-auto overflow-x-hidden">
                <img alt="Vista previa del archivo" class="block w-full h-auto">
            </div>
        </div>

        <footer class="grid grid-cols-2 gap-2 p-3 border-t border-gray-200 dark:border-white/10 shrink-0">
            <button type="button" id="visor-archivo-volver" class="va-solo-cel inline-flex items-center justify-center gap-1.5 rounded-lg bg-gray-100 dark:bg-white/10 px-4 py-2.5 text-sm font-semibold text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-white/15">Cerrar</button>
            <a id="visor-archivo-nueva" href="#" target="_blank" rel="noopener" class="va-solo-esc inline-flex items-center justify-center gap-1.5 rounded-lg bg-gray-100 dark:bg-white/10 px-4 py-2.5 text-sm font-semibold text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-white/15">Nueva pestaña</a>
            <a id="visor-archivo-descargar" href="#" download class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-tinta-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-tinta-800">Descargar</a>
        </footer>
    </aside>

    <div id="visor-archivo-chip" class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/10 rounded-xl shadow-lg px-3 py-2">
        <p id="visor-archivo-chip-titulo" class="flex-1 min-w-0 text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">Archivo adjunto</p>
        <button type="button" id="visor-archivo-chip-ver" class="text-sm font-semibold text-tinta-600 dark:text-tinta-300 px-2 py-1">Ver</button>
        <button type="button" id="visor-archivo-chip-x" aria-label="Cerrar vista previa" class="inline-flex items-center justify-center size-8 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
</div>

<script>
    (() => {
        const visor = document.getElementById('visor-archivo');
        if (!visor) return;
        const $ = (id) => document.getElementById(id);
        const panel = $('visor-archivo-panel');
        const chip = $('visor-archivo-chip');
        const frame = $('visor-archivo-frame');
        const contenedorImagen = $('visor-archivo-imagen');
        const imagen = contenedorImagen.querySelector('img');
        const titulo = $('visor-archivo-titulo');
        const chipTitulo = $('visor-archivo-chip-titulo');
        const tabs = $('visor-archivo-tabs');
        const botonDescargar = $('visor-archivo-descargar');
        const botonNueva = $('visor-archivo-nueva');
        const contenido = document.getElementById('contenido');
        const escritorio = window.matchMedia('(min-width: 1024px)');

        const ESTILOS_TAB = {
            base: 'shrink-0 rounded-full px-3 py-1 text-xs font-semibold border ',
            activo: 'bg-tinta-700 text-white border-tinta-700',
            inactivo: 'bg-transparent text-gray-600 dark:text-gray-300 border-gray-300 dark:border-white/20 hover:bg-gray-100 dark:hover:bg-white/10',
        };
        const GENERICAS = ['ver', 'ver archivo', 'ver adjunto', 'abrir'];

        let archivos = [];
        let actual = -1;

        const esArchivo = (a) => /\/archivo(\/|\?|$)/.test(a.getAttribute('href') || '');

        const etiqueta = (a) => {
            const texto = a.textContent.trim();
            if (texto && !GENERICAS.includes(texto.toLowerCase())) return texto;
            const href = a.getAttribute('href') || '';
            if (href.includes('/sustentos/')) return 'Sustento';
            if (href.includes('respuesta-posthoc')) return 'Respuesta';
            return 'Archivo';
        };

        const recolectar = () => {
            const vistos = new Set();
            archivos = [];
            document.querySelectorAll('a[href]').forEach((a) => {
                if (a.closest('#visor-archivo') || !esArchivo(a) || vistos.has(a.href)) return;
                vistos.add(a.href);
                archivos.push({ href: a.href, texto: etiqueta(a) });
            });
        };

        const pintarTabs = () => {
            tabs.textContent = '';
            tabs.classList.toggle('hidden', archivos.length < 2);
            tabs.classList.toggle('flex', archivos.length >= 2);
            archivos.forEach((archivo, i) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('role', 'tab');
                b.setAttribute('aria-selected', i === actual ? 'true' : 'false');
                b.className = ESTILOS_TAB.base + (i === actual ? ESTILOS_TAB.activo : ESTILOS_TAB.inactivo);
                b.textContent = archivo.texto;
                b.addEventListener('click', () => cargar(i));
                tabs.appendChild(b);
            });
        };

        // El archivo se ajusta al ancho del panel y solo se desplaza en vertical:
        // las imágenes se muestran al 100 % de ancho y los PDF se abren con "ajustar al ancho".
        const PARAMS_PDF = '#toolbar=0&navpanes=0&view=FitH&zoom=page-width';
        let turno = 0;

        const vaciarVista = () => {
            frame.src = 'about:blank';
            imagen.removeAttribute('src');
        };

        const mostrar = (href, esImagen) => {
            vaciarVista();
            frame.classList.toggle('hidden', esImagen);
            contenedorImagen.classList.toggle('hidden', !esImagen);
            if (esImagen) {
                imagen.src = href;
                contenedorImagen.scrollTop = 0;
            } else {
                frame.src = href + PARAMS_PDF;
            }
        };

        const cargar = (i) => {
            actual = i;
            const archivo = archivos[i];
            titulo.textContent = chipTitulo.textContent = archivo.texto;
            botonDescargar.href = botonNueva.href = archivo.href;
            pintarTabs();
            const mio = ++turno;
            vaciarVista();
            // Se consulta el tipo de archivo sin descargarlo; si falla, se usa el visor de PDF.
            fetch(archivo.href, { method: 'HEAD', credentials: 'same-origin' })
                .then((r) => (r.headers.get('content-type') || '').startsWith('image/'))
                .catch(() => false)
                .then((esImagen) => { if (mio === turno) mostrar(archivo.href, esImagen); });
        };

        // La página deja libre el espacio que ocupa el visor para que nada quede tapado.
        // En escritorio el espacio se reserva en el contenedor padre (no en #contenido), así
        // #contenido conserva su "margin: auto" y queda centrado en lo que sobra.
        const zona = contenido ? contenido.parentElement : null;
        const ajustarPagina = () => {
            if (!contenido) return;
            const transicion = 'padding-right 200ms ease, padding-bottom 200ms ease';
            contenido.style.transition = zona.style.transition = transicion;
            zona.style.paddingRight = '';
            contenido.style.paddingBottom = '';
            const estado = visor.dataset.estado;
            if (estado === 'cerrado') return;
            const asa = parseFloat(getComputedStyle(visor).getPropertyValue('--va-asa')) * 16 || 20;
            if (escritorio.matches) {
                zona.style.paddingRight = (estado === 'plegado' ? asa : panel.offsetWidth + asa) + 'px';
            } else if (estado === 'medio') {
                contenido.style.paddingBottom = 'calc(' + panel.offsetHeight + 'px + 1rem)';
            } else if (estado === 'minimizado') {
                contenido.style.paddingBottom = 'calc(' + (chip.offsetHeight + posicionChip()) + 'px + 1rem)';
            }
        };

        // El chip minimizado se apoya sobre la barra de navegación inferior si existe.
        const posicionChip = () => {
            const nav = document.querySelector('.bottom-nav');
            return (nav ? nav.offsetHeight : 0) + 8;
        };

        const poner = (estado) => {
            visor.dataset.estado = estado;
            visor.classList.toggle('hidden', estado === 'cerrado');
            visor.setAttribute('aria-hidden', estado === 'cerrado' ? 'true' : 'false');
            chip.style.bottom = posicionChip() + 'px';
            requestAnimationFrame(ajustarPagina);
        };

        const estadoInicial = () => (escritorio.matches ? 'abierto' : 'medio');

        const abrir = (href) => {
            recolectar();
            let i = archivos.findIndex((a) => a.href === href);
            if (i < 0) { archivos.push({ href, texto: 'Archivo' }); i = archivos.length - 1; }
            cargar(i);
            const estado = visor.dataset.estado;
            if (estado === 'cerrado' || estado === 'minimizado' || estado === 'plegado') poner(estadoInicial());
            $('visor-archivo-x').focus({ preventScroll: true });
        };

        const cerrar = () => {
            poner('cerrado');
            turno++;
            vaciarVista();
            actual = -1;
        };

        const alternarAmplio = () => poner(visor.dataset.estado === 'amplio' ? 'medio' : 'amplio');
        const alternarPliegue = () => poner(visor.dataset.estado === 'plegado' ? 'abierto' : 'plegado');

        $('visor-archivo-x').addEventListener('click', cerrar);
        $('visor-archivo-volver').addEventListener('click', cerrar);
        $('visor-archivo-chip-x').addEventListener('click', cerrar);
        $('visor-archivo-ampliar').addEventListener('click', alternarAmplio);
        $('visor-archivo-barra').addEventListener('click', alternarAmplio);
        $('visor-archivo-minimizar').addEventListener('click', () => poner('minimizado'));
        $('visor-archivo-chip-ver').addEventListener('click', () => poner('medio'));
        $('visor-archivo-asa').addEventListener('click', alternarPliegue);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && visor.dataset.estado !== 'cerrado') cerrar(); });

        // Al girar el celular o cambiar el tamaño de la ventana, se pasa al modo que corresponde.
        escritorio.addEventListener('change', () => {
            if (visor.dataset.estado !== 'cerrado') poner(estadoInicial());
        });

        document.addEventListener('click', (e) => {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            const enlace = e.target.closest('a[href]');
            if (!enlace || enlace.closest('#visor-archivo') || !esArchivo(enlace)) return;
            e.preventDefault();
            abrir(enlace.href);
        });
    })();
</script>