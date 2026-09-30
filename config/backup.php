<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    |
    | `php artisan backup:database` runs nightly (see routes/console.php). Dumps
    | are private (storage/app/backups) and should be copied off the server by
    | your infrastructure (object storage, another region). A backup that has
    | never been restored is not a backup: restore the latest dump into a scratch
    | database regularly and check it.
    |
    */
    'connection' => env('BACKUP_DB_CONNECTION'), // null = the default connection
    'path' => env('BACKUP_PATH', storage_path('app/backups')),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 1800),

];
