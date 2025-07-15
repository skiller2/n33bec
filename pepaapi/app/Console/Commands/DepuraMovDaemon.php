<?php

namespace App\Console\Commands;

use App\HabiCredPersona;
use App\HabiCredSectores;
use App\Helpers\ConfigParametro;
use App\MoviEvento;
use App\PermanenteOK;
use App\Rechazado;
use App\TemporalOK;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class DepuraMovDaemon extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:DepuraMovDaemon
                            {--debug : Print debug information to console}
                            {--plazo_depura_mov_permanentes=} {--plazo_depura_mov_temporales=} {--plazo_depura_mov_rechazados=} {--plazo_depura_eventos=}
                            ej: php artisan command:DepuraMovDaemon --plazo_depura_mov_permanentes=1D --plazo_depura_mov_temporales=1D --plazo_depura_mov_rechazados=1D --plazo_depura_eventos=1D';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Depura Movimientos Daemon';
    protected $plazo_depura_mov_permanentes = '';
    protected $plazo_depura_mov_temporales = '';
    protected $plazo_depura_mov_rechazados = '';

    const logFileName = "depura";

    protected $daemon_conf_ver = "";

    const confVersion = "daemon_conf_ver";

    protected function printDebugInfo($text, $status = "info") {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    protected function loadConfigData() {
        $this->plazo_depura_mov_permanentes = ConfigParametro::get('PLAZO_DEPURA_MOV_PERMANENTES', false);
        $this->plazo_depura_mov_temporales = ConfigParametro::get('PLAZO_DEPURA_MOV_TEMPORALES', false);
        $this->plazo_depura_mov_rechazados = ConfigParametro::get('PLAZO_DEPURA_MOV_RECHAZADOS', false);
        $this->plazo_depura_eventos = ConfigParametro::get('PLAZO_DEPURA_EVENTOS', false);

        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }

    /**
     * Devuelve el plazo de tiempo para eliminar registros antiguos
     * @param type $plazo ej: 24H, 2D, etc.
     * @return boolean
     */
    protected function dateDiff($plazo) {

        $arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i', $plazo);
        $valor_tiempo = (isset($arr[0])) ? $arr[0] : "";
        $unidad_medida_tiempo = (isset($arr[1])) ? $arr[1] : "";
        if ($valor_tiempo == "" || $unidad_medida_tiempo == "") {
            return false;
        }

        switch ($unidad_medida_tiempo) {
            case "H":
                $limit = Carbon::now()->subHours($valor_tiempo);
                break;
            case "D":
                $limit = Carbon::now()->subDays($valor_tiempo);
                break;
            case "M":
                $limit = Carbon::now()->subMonths($valor_tiempo);
                break;
            case "Y":
                $limit = Carbon::now()->subYears($valor_tiempo);
                break;
        }

        return $limit;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $this->loadConfigData();
        while (1) {
            sleep(60 * 60);
            if (Cache::get(self::confVersion) != $this->daemon_conf_ver)
                $this->loadConfigData();

            $this->plazo_depura_mov_permanentes = ($this->option('plazo_depura_mov_permanentes')) ? $this->option('plazo_depura_mov_permanentes') : $this->plazo_depura_mov_permanentes;
            $this->plazo_depura_mov_temporales = ($this->option('plazo_depura_mov_temporales')) ? $this->option('plazo_depura_mov_temporales') : $this->plazo_depura_mov_temporales;
            $this->plazo_depura_mov_rechazados = ($this->option('plazo_depura_mov_rechazados')) ? $this->option('plazo_depura_mov_rechazados') : $this->plazo_depura_mov_rechazados;
            $this->plazo_depura_eventos = ($this->option('plazo_depura_eventos')) ? $this->option('plazo_depura_eventos') : $this->plazo_depura_eventos;

            if ($this->plazo_depura_mov_permanentes != "") {
                $plazo = $this->dateDiff($this->plazo_depura_mov_permanentes);
                if ($plazo) {
                    $cant_eliminados = 0;
                    do {
                        $permanente = PermanenteOK::where('stm_movimiento', '<', $plazo)->limit(2)->delete();
                        $cant_eliminados += $permanente;
                    } while ($permanente);
                    if ($cant_eliminados > 0)
                        Log::channel(self::logFileName)->info('Se eliminaron #' . $cant_eliminados . ' registros de la tabla movimientos permitidos permanentes', array());

                        
                }
            }
            if ($this->plazo_depura_mov_temporales != "") {
                $plazo = $this->dateDiff($this->plazo_depura_mov_temporales);
                if ($plazo) {
                    $cant_eliminados = 0;
                    do {
                        $temporal = TemporalOK::where('stm_movimiento', '<', $plazo)->limit(2)->delete();
                        $cant_eliminados += $temporal;
                    } while ($temporal);
                    if ($cant_eliminados > 0)
                        Log::channel(self::logFileName)->info('Se eliminaron #' . $cant_eliminados . ' registros de la tabla movimientos permitidos temporales', array());

                }
            }
            if ($this->plazo_depura_mov_rechazados != "") {
                $plazo = $this->dateDiff($this->plazo_depura_mov_rechazados);
                if ($plazo) {
                    $cant_eliminados = 0;
                    do {
                        $rechazado = Rechazado::where('stm_movimiento', '<', $plazo)->limit(2)->delete();
                        $cant_eliminados += $rechazado;
                    } while ($rechazado);
                    if ($cant_eliminados > 0)
                        Log::channel(self::logFileName)->info('Se eliminaron #' . $cant_eliminados . ' registros de la tabla movimientos rechazados', array());
                }
            }
            if ($this->plazo_depura_eventos != "") {
                $plazo = $this->dateDiff($this->plazo_depura_eventos);
                if ($plazo) {
                    $cant_eliminados = 0;
                    do {
                        $eventos = MoviEvento::where('stm_evento', '<', $plazo)->limit(2)->delete();
                        $cant_eliminados += $eventos;
                    } while ($eventos);
                    if ($cant_eliminados > 0)
                    Log::channel(self::logFileName)->info('Se eliminaron #' . $cant_eliminados . ' registros de la tabla eventos', array());
                }
            }
        }
    }

}
