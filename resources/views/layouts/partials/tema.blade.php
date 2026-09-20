{{--
    Color de la barra del navegador + anti-parpadeo del modo oscuro.
    Compartido por los tres layouts (app, guest y trabajador) para que no
    diverjan: antes cada uno tenía su copia y su propio azul.

    theme-color = tinta-900 (#1b2b34), el mismo de public/manifest.json.

    La clave ('mssc-theme') y los valores ('light' | 'dark' | ausente =
    automático) tienen que coincidir con resources/js/theme.js. Se aplica
    ANTES de pintar para que no haya destello claro en modo oscuro.
--}}
<meta name="theme-color" content="#1b2b34">
<script>
    (function () {
        var modo = localStorage.getItem('mssc-theme');
        var oscuro = modo === 'dark' || (modo === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', oscuro);
    })();
</script>
