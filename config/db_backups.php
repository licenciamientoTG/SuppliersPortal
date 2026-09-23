<?php

return [

    /*
    | Correos autorizados para generar, ver y descargar respaldos (CSV en .env).
    */
    'allowed_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('DB_BACKUP_ALLOWED_EMAILS', 'aldo.ochoa@totalgas.com,daniel.ramirez@totalgas.com'))
    ))),

    /*
    | Carpeta destino tal como la ve SQL Server (ruta UNC al share del servidor de la app).
    */
    'sql_path' => env('DB_BACKUP_SQL_PATH'),

    /*
    | La misma carpeta vista desde la aplicación.
    */
    'local_path' => env('DB_BACKUP_LOCAL_PATH', storage_path('app/private/backups')),

    'keep' => (int) env('DB_BACKUP_KEEP', 3),

    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 1800),

];
