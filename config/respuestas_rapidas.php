<?php

/*
 * Textos de un clic para los comentarios que RRHH escribe todo el día al
 * observar o rechazar una papeleta (ver <x-accion-comentario :sugerencias>).
 * Solo rellenan el cuadro de texto: RRHH puede editarlos antes de confirmar.
 */
return [
    'rrhh_observar' => [
        'Falta precisar la hora estimada de retorno.',
        'El motivo no coincide con lo descrito. Aclarar o corregir el motivo.',
        'Adjuntar el documento de sustento correspondiente.',
        'Falta indicar el lugar o la institución a la que se dirige.',
    ],
    'rrhh_rechazar' => [
        'El motivo no corresponde a una salida autorizable.',
        'No se presentó el sustento exigido para este motivo.',
        'La papeleta se solicitó fuera del plazo permitido.',
        'Existe otra papeleta del mismo día que se superpone.',
    ],
    'rrhh_posthoc_observar' => [
        'Falta justificar por qué se autorizó fuera del horario de RRHH.',
        'Adjuntar sustento de la urgencia que motivó la autorización.',
    ],
];
