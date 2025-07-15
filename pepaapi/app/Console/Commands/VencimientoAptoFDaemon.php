<?php

namespace App\Console\Commands;

use App\AptoFisico;
use Illuminate\Support\Facades\Log;
use App\Helpers\ConfigParametro;
use App\Jobs\SendMail;
use App\MoviEvento;
use App\PermanenteOK;
use App\Rechazado;
use App\TemporalOK;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class VencimientoAptoFDaemon extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:VencimientoAptoFDaemon
                            {--debug : Print debug information to console}
                            {--plazo_previo_vto_apto_fisico=}
                            ej: php artisan command:VencimientoAptoFDaemon --plazo_previo_vto_apto_fisico=7D';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chequea Vencimiento Apto Físico Daemon';
    protected $plazo_previo_vto_apto_fisico = '';

    const logFileName = "aptofisicos";

    protected $daemon_conf_ver = "";

    const confVersion = "daemon_conf_ver";

    protected function printDebugInfo($text, $status = "info") {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    protected function loadConfigData() {
        $this->plazo_previo_vto_apto_fisico = ConfigParametro::get('PLAZO_PREVIO_VTO_APTO_FISICO', false);

        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }

    /**
     * Devuelve el plazo de tiempo
     * @param type $plazo ej: 24H, 2D, etc.
     * @return boolean
     */
    protected function dateDiff($plazo_previo_vto_apto_fisico) {

        $arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i', $plazo_previo_vto_apto_fisico);
        $valor_tiempo = (isset($arr[0])) ? $arr[0] : "";
        $unidad_medida_tiempo = (isset($arr[1])) ? $arr[1] : "";
        if ($valor_tiempo == "" || $unidad_medida_tiempo == "") {
            return false;
        }

        switch ($unidad_medida_tiempo) {
            case "H":
                $limit = Carbon::now()->addHours($valor_tiempo);
                break;
            case "D":
                $limit = Carbon::now()->addDays($valor_tiempo);
                break;
            case "M":
                $limit = Carbon::now()->addMonths($valor_tiempo);
                break;
            case "Y":
                $limit = Carbon::now()->addYears($valor_tiempo);
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

            $this->plazo_previo_vto_apto_fisico = ($this->option('plazo_previo_vto_apto_fisico')) ? $this->option('plazo_previo_vto_apto_fisico') : $this->plazo_previo_vto_apto_fisico;

            if ($this->plazo_previo_vto_apto_fisico !== "") {
                $plazo = $this->dateDiff($this->plazo_previo_vto_apto_fisico);
                if ($plazo) {
                    $stm_actual = Carbon::now();
                    $personas = AptoFisico::select('maesPersAptoF.fec_vencimiento_af','maesPersonas.email',
                    'maesPersonas.nom_persona', 'maesPersonas.ape_persona', 'maesPersAptoF.cod_persona')
                    ->join('maesPersonas', 'maesPersonas.cod_persona', '=', 'maesPersAptoF.cod_persona')
                    ->where('maesPersAptoF.fec_vencimiento_af', '<', $plazo)
                    ->whereNull('maesPersAptoF.stm_notificacion')
                    ->orWhere('maesPersAptoF.stm_notificacion', '=', '0000-00-00 00:00:00')
                    ->get();
                    
                    foreach ($personas as $persona) {
                        if(!$persona->email || $persona->email == '')
                            continue;
                        
                        $data = array();
                        $data['des_destinatarios'] = array($persona->email);
                        $data['des_asunto'] = 'VENCIMIENTO APTO FISICO';
                        $data['fec_vencimiento_af'] = Carbon::parse($persona->fec_vencimiento_af);
                        $data['ape_persona'] = $persona->ape_persona;
                        $data['nom_persona'] = $persona->nom_persona;
                        
                        $job = (new SendMail('MAIL_AF_TMPL', $data));
                        dispatch($job->onQueue("low"));

                        $persona->stm_notificacion = $stm_actual;
                        $persona->save();
                    }
                }
            }
        }
    }

}
