@props(['disabled' => false])

{{--
    Complemento de <x-input> para <select>: mismo tratamiento visual
    (borde tinta, fondo con backdrop-blur, foco tinta-500) para que un
    filtro en <select> no se vea como un control de otro sistema al lado
    de un <x-input> de texto. Antes cada <select> de filtro llevaba su
    propia clase suelta (w-full rounded-md border-gray-300 dark:bg-gray-800).

    dark:[color-scheme:dark] — la lista de <option> al desplegarse la
    dibuja el navegador con sus controles nativos, no con nuestro CSS.
    `.dark { color-scheme: dark }` en app.css lo declara en <html>, pero
    esa herencia no basta en todos los navegadores para que el propio
    <select> la respete al abrir el desplegable (se ve blanco encima de
    la página oscura). Declarándolo también aquí, directamente en el
    control, el desplegable nativo sí sale oscuro.
--}}

<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-xl border-tinta-200 dark:border-white/15 bg-white/70 dark:bg-white/10 dark:text-white dark:[color-scheme:dark] backdrop-blur focus:border-tinta-500 focus:ring-tinta-500 shadow-sm transition']) !!}>
    {{ $slot }}
</select>
