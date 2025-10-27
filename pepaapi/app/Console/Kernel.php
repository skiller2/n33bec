<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        
      Commands\DepuraMovDaemon::class,
      Commands\IODaemon::class,
      Commands\Rs485Daemon::class,
      Commands\Area54Daemon::class,
      Commands\ActualizaHabiAcceso::class,
      Commands\ActualizaMovimientos::class,
//      Commands\RestartFatalSupervisor::class,
      Commands\DelayedTemaDaemon::class,
//      Commands\WebSocketServer::class,
      Commands\MessagesDaemon::class,
      Commands\CredencialesDaemon::class,
      Commands\MoviDisplayTemasDaemon::class,
      Commands\AudioEvacDaemon::class,
      Commands\ActuadoresDaemon::class,
      Commands\SincronizaAsistencia::class,
      Commands\VencimientoAptoFDaemon::class,
      Commands\MoviDisplayTemasRemoteDaemon::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
