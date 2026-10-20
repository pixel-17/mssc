@props(['papeleta'])

{{--
    Desactivado: esta papeleta ya no se auto-actualiza en vivo mientras se
    está viendo/escribiendo en ella (el refresco silencioso por Reverb +
    el aviso "cambió de estado mientras escribías" generaban confusión).
    Las bandejas (Livewire) siguen refrescándose solas como antes; el
    detalle de una papeleta ahora solo se actualiza si la persona
    recarga la página.

    Se deja el componente vacío (en vez de borrar sus usos en las vistas)
    para no tener que tocar los `data-en-vivo-estado` / `data-en-vivo-contenido`
    de cada show.blade.php si en el futuro se decide reactivarlo.
--}}
