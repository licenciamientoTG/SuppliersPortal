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
    | Carpeta en el disco del servidor SQL donde se escribe el .bak temporal antes de
    | copiarlo al portal. Opcional: si se omite se usa la carpeta de respaldos
    | predeterminada de la instancia (SERVERPROPERTY('InstanceDefaultBackupPath')).
    */
    'sql_path' => env('DB_BACKUP_SQL_PATH'),

    /*
    | Carpeta del portal donde se guardan las copias.
    */
    'local_path' => env('DB_BACKUP_LOCAL_PATH', storage_path('app/private/backups')),

    'keep' => (int) env('DB_BACKUP_KEEP', 3),

    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 1800),

];
