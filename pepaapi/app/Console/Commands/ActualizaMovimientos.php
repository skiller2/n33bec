<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Helpers\ConfigParametro;
use App\Http\Controllers\Movimientos;
use App\Parametro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

class ActualizaMovimientos extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ActualizaMovimientos
                            {--debug : Print debug information to console}
                            ej: php artisan command:ActualizaMovimientos --debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza Movimientos';
    protected $habiAccesoParams = array('urlMaster' => '', 'pageSize' => 100, 'syncIntervalSeg' => 60 * 5);
    protected $daemon_conf_ver = "";
    protected $lastUpdate = array('permanentes' => '0000-00-00 00:00:00', 'temporales' => '0000-00-00 00:00:00', 'rechazados' => '0000-00-00 00:00:00');

    const logFileName = "syncmov";
    const confVersion = "daemon_conf_ver";
    const config_tag = "config_";

    protected function printDebugInfo($text, $status = 'info')
    {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    protected function loadConfigData()
    {
        $habiAccesoParams = ConfigParametro::get('HABI_ACCESO', true);
        if (!empty($habiAccesoParams)) {
            $this->habiAccesoParams = $habiAccesoParams;
            if (!isset($this->habiAccesoParams['urlMaster'])) $this->habiAccesoParams['urlMaster'] = '';
            if (!isset($this->habiAccesoParams['pageSize'])) $this->habiAccesoParams['pageSize'] = 100;
            if (!isset($this->habiAccesoParams['syncIntervalSeg'])) $this->habiAccesoParams['syncIntervalSeg'] = 60 * 5;
        }

        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->printDebugInfo("Inicia");
        $this->loadConfigData();

        $parametro = new Parametro;
        $parametro->den_parametro = "MOV_LAST_UPDATE";
        $parametro->val_parametro = json_encode(array('permanentes' => '0000-00-00 00:00:00', 'temporales' => '0000-00-00 00:00:00', 'rechazados' => '0000-00-00 00:00:00'));
        $parametro->des_parametro = "Movimientos Last Update";
        Parametro::addAuditoria($parametro, "A");
        try {
            $parametro->save();
        } catch (\Exception $e) {
        }

        while (1) {
            if (Cache::get(self::confVersion) != $this->daemon_conf_ver)
                $this->loadConfigData();

            if ($this->habiAccesoParams['urlMaster'] === '')
                continue;

            $this->printDebugInfo("Actualizando movimientos");
            $lastUpdateParams = ConfigParametro::get('MOV_LAST_UPDATE', true);
//echo "leo ".var_export($lastUpdateParams,true)." \n";
            if (!empty($lastUpdateParams)) {
                $this->lastUpdate = $lastUpdateParams;
                if (!isset($this->lastUpdate['permanentes'])) $this->lastUpdate['permanentes'] = '0000-00-00 00:00:00';
                if (!isset($this->lastUpdate['temporales'])) $this->lastUpdate['temporales'] = '0000-00-00 00:00:00';
                if (!isset($this->lastUpdate['rechazados'])) $this->lastUpdate['rechazados'] = '0000-00-00 00:00:00';
            }

//            echo "Inicio Actualizando \n";
//            echo "lastUpdate " . var_export($this->lastUpdate, true) . " \n";

            //Log::channel(self::logFileName)->error($message, array());
            $vvrespuesta = Movimientos::syncPermanentes($this->lastUpdate['permanentes'], $this->habiAccesoParams['urlMaster']);
            $ind_grabacion_permanentes = $vvrespuesta['ind_grabacion'];
            if ($ind_grabacion_permanentes)
                $this->lastUpdate['permanentes'] = $vvrespuesta['lastUpdate'];
            $errors_permanentes = $vvrespuesta['errors'];

            $vvrespuesta = Movimientos::syncTemporales($this->lastUpdate['temporales'], $this->habiAccesoParams['urlMaster']);
            $ind_grabacion_temporales = $vvrespuesta['ind_grabacion'];
            if ($ind_grabacion_temporales)
                $this->lastUpdate['temporales'] = $vvrespuesta['lastUpdate'];
            $errors_temporales = $vvrespuesta['errors'];

            $vvrespuesta = Movimientos::syncRechazados($this->lastUpdate['rechazados'], $this->habiAccesoParams['urlMaster']);
            $ind_grabacion_rechazados = $vvrespuesta['ind_grabacion'];
            if ($ind_grabacion_rechazados)
                $this->lastUpdate['rechazados'] = $vvrespuesta['lastUpdate'];
            $errors_rechazados = $vvrespuesta['errors'];


            if ($ind_grabacion_rechazados || $ind_grabacion_temporales || $ind_grabacion_permanentes) {
//echo "Grabo valores de update \n";
                $parametro = Parametro::find('MOV_LAST_UPDATE');
                $parametro->val_parametro = json_encode($this->lastUpdate, true);
                Parametro::addAuditoria($parametro, "M");
                $parametro->save();
                Cache::forget(self::config_tag ."MOV_LAST_UPDATE");
            }

            foreach ($errors_permanentes as $error)
                Log::channel(self::logFileName)->error($error[1], array());
            foreach ($errors_rechazados as $error)
                Log::channel(self::logFileName)->error($error[1], array());
            foreach ($errors_temporales as $error)
                Log::channel(self::logFileName)->error($error[1], array());


//            echo "Fin Actualizando \n";
            $this->printDebugInfo("Fin actualización movimientos");

            sleep($this->habiAccesoParams['syncIntervalSeg']);
        }
    }
}
