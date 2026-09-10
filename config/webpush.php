<?php

use NotificationChannels\WebPush\PushSubscription;

return [

    /**
     * Claves de autenticación (VAPID) del canal web push.
     * Generar con `php artisan webpush:vapid` y NUNCA versionarlas:
     * deben vivir solo en el .env de cada entorno.
     */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /**
     * Modelo usado para las suscripciones push. Coincide con la migración
     * database/migrations/2026_09_04_161839_create_push_subscriptions_table.php
     * ya presente en el repo.
     */
    'model' => PushSubscription::class,

    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    'client_options' => [],

    /**
     * Padding automático (Minishlink\WebPush). En true por defecto;
     * desactivar solo si se necesita soportar Firefox Android con
     * endpoint v1.
     */
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
