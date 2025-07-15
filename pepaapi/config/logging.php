<?php

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],

        'acceso' => [
            'driver' => 'daily',
            'path' => storage_path('logs/acceso.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'movidisplaytema' => [
            'driver' => 'daily',
            'path' => storage_path('logs/movidisplaytema.log'),
            'level' => 'debug',
            'days' => 14,
        ],


        'habiacceso' => [
            'driver' => 'daily',
            'path' => storage_path('logs/habiacceso.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'eventos' => [
            'driver' => 'daily',
            'path' => storage_path('logs/eventos.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'io' => [
            'driver' => 'daily',
            'path' => storage_path('logs/io.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'syncmov' => [
            'driver' => 'daily',
            'path' => storage_path('logs/syncmov.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'credenciales' => [
            'driver' => 'daily',
            'path' => storage_path('logs/credenciales.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'sucesos' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sucesos.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'messages' => [
            'driver' => 'daily',
            'path' => storage_path('logs/messages.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'actuadorestema' => [
            'driver' => 'daily',
            'path' => storage_path('logs/actuadorestema.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'area54' => [
            'driver' => 'daily',
            'path' => storage_path('logs/area54.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'delayedio' => [
            'driver' => 'daily',
            'path' => storage_path('logs/delayedio.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'depura' => [
            'driver' => 'daily',
            'path' => storage_path('logs/depura.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'serial' => [
            'driver' => 'daily',
            'path' => storage_path('logs/serial.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'syncasistencia' => [
            'driver' => 'daily',
            'path' => storage_path('logs/syncasistencia.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'aptofisicos' => [
            'driver' => 'daily',
            'path' => storage_path('logs/aptofisicos.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'mail' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mail.log'),
            'level' => 'debug',
            'days' => 14,
        ],


        'daily' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],
    ],

];
