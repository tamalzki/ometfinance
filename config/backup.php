<?php

return [

    'backup' => [

        'name' => env('APP_NAME', 'OMET Finance'),

        'source' => [

            'files' => [
                // Code lives in Git — exclude all files, back up the database only.
                // Keeping this empty means no file archive is added to the zip.
                'include' => [],

                'exclude' => [],

                'follow_links' => false,

                'ignore_unreadable_directories' => false,

                'relative_path' => null,
            ],

            /*
             * MySQL connection defined in config/database.php.
             * useSingleTransaction avoids table-level locks on InnoDB tables
             * so the live app is unaffected while the dump runs.
             */
            'databases' => [
                'mysql',
            ],
        ],

        // Gzip the SQL dump before zipping — cuts file size by ~70 %.
        'database_dump_compressor' => \Spatie\DbDumper\Compressors\GzipCompressor::class,

        'database_dump_file_extension' => '',

        'destination' => [
            'filename_prefix' => 'omet-backup-',

            'disks' => [
                'do-spaces',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // Encrypt the backup zip archive with a password (store in .env).
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',
    ],

    /*
     * Notifications — only alert on failure or unhealthy state.
     * Success emails every day get noisy fast, so they are disabled.
     */
    'notifications' => [

        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class          => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class  => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class         => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class      => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class    => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class     => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
                'name'    => env('MAIL_FROM_NAME', 'OMET Finance'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel'     => null,
            'username'    => null,
            'icon'        => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username'    => null,
            'avatar_url'  => null,
        ],
    ],

    'monitor_backups' => [
        [
            'name'  => env('APP_NAME', 'OMET Finance'),
            'disks' => ['do-spaces'],
            'health_checks' => [
                // Alert if the newest backup is older than 2 days (gives 1 day buffer).
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class           => 2,
                // Alert if backup storage exceeds 5 GB.
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class  => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days'                       => 7,   // keep every backup for 7 days
            'keep_daily_backups_for_days'                     => 30,  // then 1 per day for 30 days
            'keep_weekly_backups_for_weeks'                   => 12,  // then 1 per week for 12 weeks
            'keep_monthly_backups_for_months'                 => 12,  // then 1 per month for 12 months
            'keep_yearly_backups_for_years'                   => 2,   // then 1 per year for 2 years
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],

];
