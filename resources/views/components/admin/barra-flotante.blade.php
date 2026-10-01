{{--
    Barra de acciones (Guardar / Cancelar) de los formularios de edición.
    Queda pegada al borde inferior de la pantalla mientras el formulario es
    más largo que la ventana, así no hay que bajar hasta el final para
    guardar. Dentro del formulario sigue siendo un elemento normal: al llegar
    al final se queda en su sitio.
--}}
<div {{ $attributes->merge(['class' => 'sticky bottom-4 z-30 flex items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white/90 px-4 py-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-gray-900/90']) }}
     style="bottom: max(1rem, env(safe-area-inset-bottom, 0px));">
    {{ $slot }}
</div>
