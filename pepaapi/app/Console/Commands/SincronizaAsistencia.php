<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Helpers\ConfigParametro;
use App\Http\Controllers\Asis\Registros;
use Carbon\Carbon;

class SincronizaAsistencia extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:SincronizaAsistencia
                            {--debug : Print debug information to console}
                            ej: php artisan command:SincronizaAsistencia --debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinctroniza Tablas Asistencia';
    protected $daemon_conf_ver = '';
    protected $lastSync = '';

    const logFileName = 'syncasistencia';
    const confVersion = 'daemon_conf_ver';

    protected function printDebugInfo($text, $status = 'info') {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    protected function loadConfigData() {
        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $this->printDebugInfo("Inicia");
        $this->loadConfigData();
        $ind_sincronizado = false;

        while (1) {
            if (Cache::get(self::confVersion) != $this->daemon_conf_ver)
                $this->loadConfigData();
                
            $now = Carbon::now();
            $hour = $now->format('H');

            if ($hour == '00' && (!$this->lastSync || !$this->lastSync->isCurrentDay())){
                
                $this->printDebugInfo("Sincronizando tablas asistencia");
                $context=array(
                    'msgtext'=>"Sincronizando tablas asistencia",
                );
                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'warning',  $context);
        

                $vvrespuesta = Registros::syncAsis();
                $vaerrors = $vvrespuesta['errors'];
                foreach($vaerrors as $error) {
                    $message = $error[1];
                    Log::channel(self::logFileName)->error($message, array());
                }

                $this->printDebugInfo("Fin sincronización tablas asistencia");

                $context=array(
                    'msgtext'=>"Tablas asistencia actualizadas",
                );
                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);

                $this->lastSync = $now;                
            }
            sleep(60*5);
        }
	}
}
