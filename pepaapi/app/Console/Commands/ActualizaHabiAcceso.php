<?php

namespace App\Console\Commands;

use App\HabiAcceso;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use App\Http\Controllers\HabiAccesos;
use App\Http\Controllers\Temas;
use App\Http\Controllers\Esquemas;
use App\Tema;
use App\Sector;
use App\Esquema;
use App\Helpers\ConfigParametro;
use App\HabiSectoresxOU;
use App\UnidadesOrganiz;

class ActualizaHabiAcceso extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ActualizaHabiAcceso
                            {--debug : Print debug information to console}
                            ej: php artisan command:ActualizaHabiAcceso --debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza HabiAcceso';
    protected $habiAccesoParams = array('urlMaster' => '', 'pageSize' => 100, 'syncIntervalSeg' => 60 * 5);
    protected $daemon_conf_ver = "";
    protected $tema_local = "";
    const logFileName = "habiacceso";
    const confVersion = "daemon_conf_ver";

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
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));

        if (!empty($habiAccesoParams)) {
            $this->habiAccesoParams = $habiAccesoParams;
            if (!isset($this->habiAccesoParams['urlMaster'])) $this->habiAccesoParams['urlMaster'] = '';
            if (!isset($this->habiAccesoParams['pageSize'])) $this->habiAccesoParams['pageSize'] = 100;
            if (!isset($this->habiAccesoParams['syncIntervalSeg'])) $this->habiAccesoParams['syncIntervalSeg'] = 60 * 5;
            if (!isset($this->habiAccesoParams['temas'])) $this->habiAccesoParams['temas'] = '';
        }
        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }

    /**
     * Devuelve el plazo de tiempo para eliminar registros antiguos
     * @param type $plazo ej: 24H, 2D, etc.
     * @return boolean
     */
    protected function dateDiff($plazo)
    {

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

    protected function getLastUpdate()
    {
        $lastUpdate = "0000-00-00 00:00:00";
        $client = new Client(['verify' => false]);
        try {
            $res = $client->request('GET', $this->habiAccesoParams['urlMaster'] . '/habiaccesos/getLastUpdate');
            if ($res->getStatusCode() == 200) { // 200 OK
                $lastUpdate = $res->getBody()->getContents();
            }
        } catch (\Exception $e) {
            Log::channel(self::logFileName)->error($e->getMessage(), array());
        }

        return $lastUpdate;
    }

    protected function getDataFromMaster($urlMasterSync, $params = [])
    {
        $response = array("data" => array(), 'next_page_url' => false);
        $client = new Client(['verify' => false]);
        try {
            $res = $client->request('GET', $urlMasterSync, $params);
            if ($res->getStatusCode() == 200) { // 200 OK
                $response = json_decode($res->getBody()->getContents(), true);
            }
        } catch (\Exception $e) {
            Log::channel(self::logFileName)->error($e->getMessage(), array());
        }
        return $response;
    }

    protected function syncHabiAcceso()
    {
        $cambio = false;
        $url = $this->habiAccesoParams['urlMaster'] . '/habiaccesos/sync';
        HabiAcceso::select()->update(['aud_usuario_ingreso' => '']);
        $currentPage = 1;
        $params = ['query' => ['pageSize' => $this->habiAccesoParams['pageSize'], 'page' => $currentPage]];
        do {
            $params['query']['page'] = $currentPage;
            $result = $this->getDataFromMaster($url, $params);
            $next_page_url = $result['next_page_url'];
            if (!empty($result['data']))
                $cambio = true;
            foreach ($result['data'] as $row) {
                HabiAcceso::updateOrCreate(
                    [
                        'cod_credencial' => $row['cod_credencial']
                    ],
                    [
                        'ref_credencial' => $row['ref_credencial'],
                        'cod_persona' => $row['cod_persona'],
                        'nom_persona' => $row['nom_persona'],
                        'ape_persona' => $row['ape_persona'],
                        'cod_sexo' => $row['cod_sexo'],
                        'cod_tipo_doc' => $row['cod_tipo_doc'],
                        'nro_documento' => $row['nro_documento'],
                        'tipo_habilitacion' => $row['tipo_habilitacion'],
                        'obs_habilitacion' => $row['obs_habilitacion'],
                        'cod_grupo' => $row['cod_grupo'],
                        'cod_ou_hab' => $row['cod_ou_hab'],
                        'nom_ou_hab' => $row['nom_ou_hab'],
                        'cod_persona_contacto' => $row['cod_persona_contacto'],
                        'nom_persona_contacto' => $row['nom_persona_contacto'],
                        'ape_persona_contacto' => $row['ape_persona_contacto'],
                        'cantidad_ingresos' => $row['cantidad_ingresos'],
                        'json_temas' => ($this->habiAccesoParams['temas'])? $this->habiAccesoParams['temas']:$row['json_temas'],
                        'cod_esquema_acceso' => $row['cod_esquema_acceso'],
                        'stm_habilitacion_hasta' => $row['stm_habilitacion_hasta'],
                        'aud_usuario_ingreso' => $row['aud_usuario_ingreso'],
                        'aud_stm_ingreso' => $row['aud_stm_ingreso'],
                        'aud_ip_ingreso' => $row['aud_ip_ingreso']
                    ]
                );
            }
            $currentPage++;
        } while ($next_page_url);

        if ($cambio)
            HabiAcceso::where('aud_usuario_ingreso', '')->delete();
    }

    protected function syncTemas()
    {
        $cambio = false;

        $url = $this->habiAccesoParams['urlMaster'] . '/temas/sync';
        Tema::select()->update(['aud_usuario_ingreso' => '']);
        $result = $this->getDataFromMaster($url);
        if (!empty($result['data']))
            $cambio = true;

        foreach ($result['data'] as $row) {
            if (strpos($row['cod_tema'], $this->tema_local) !== 0)
                continue;
            Tema::updateOrCreate(
                [
                    'cod_tema' => $row['cod_tema']
                ],
                [
                    'nom_tema' => $row['nom_tema'],
                    'url_envio' => $row['url_envio'],
                    'cod_sector' => $row['cod_sector'],
                    'des_ubicacion' => $row['des_ubicacion'],
                    'cod_tipo_uso' => $row['cod_tipo_uso'],
                    'json_posicion_img' => ($row['json_posicion_img']) ? $row['json_posicion_img'] : "{}",
                    'json_parametros' => ($row['json_posicion_img']) ? $row['json_parametros'] : "{}",
                    'json_subtemas' => ($row['json_posicion_img']) ? $row['json_subtemas'] : "[]",

                    'ind_mostrar_en_panel' => $row['ind_mostrar_en_panel'],
                    'ind_activo' => $row['ind_activo'],
                    'ind_registra_evento' => $row['ind_registra_evento'],
                    'ind_display_evento' => $row['ind_display_evento'],
                    'ind_notifica_evento' => $row['ind_notifica_evento'],
                    'aud_ip_ingreso' => $row['aud_ip_ingreso'],
                    'aud_usuario_ingreso' => ($row['aud_usuario_ingreso']) ? $row['aud_usuario_ingreso'] : "none",

                    'aud_usuario_ultmod' => ($row['aud_usuario_ultmod']) ? $row['aud_usuario_ultmod'] : "none",
                    'aud_stm_ultmod' => $row['aud_stm_ultmod'],
                    'aud_ip_ultmod' => $row['aud_ip_ultmod']
                ]
            );
        }

        if ($cambio) {
            Tema::where('aud_usuario_ingreso', '')->delete();
            Temas::cleanCaches();
        }
    }

    protected function syncSectores()
    {
        $cambio = false;

        $url = $this->habiAccesoParams['urlMaster'] . '/sectores/sync';
        Sector::select()->update(['aud_usuario_ingreso' => '']);
        $result = $this->getDataFromMaster($url);
        if (!empty($result['data']))
            $cambio = true;

        foreach ($result['data'] as $row) {
            Sector::updateOrCreate(
                [
                    'cod_sector' => $row['cod_sector']
                ],
                [
                    'cod_referencia' => $row['cod_referencia'],
                    'nom_sector' => $row['nom_sector'],
                    'des_sector' => $row['des_sector'],
                    'des_abrev_sectores' => $row['des_abrev_sectores'],
                    'des_ubicacion' => $row['des_ubicacion'],
                    'aud_usuario_ingreso' => $row['aud_usuario_ingreso'],
                    'aud_stm_ingreso' => $row['aud_stm_ingreso'],
                    'aud_ip_ingreso' => $row['aud_ip_ingreso'],
                    'aud_usuario_ultmod' => $row['aud_usuario_ultmod'],
                    'aud_stm_ultmod' => $row['aud_stm_ultmod'],
                    'aud_ip_ultmod' => $row['aud_ip_ultmod']
                ]
            );
        }

        if ($cambio) {
            Sector::where('aud_usuario_ingreso', '')->delete();
            Cache::forget("SECTORES");
        }
    }

    protected function syncEsquemas()
    {
        $cambio = false;

        $url = $this->habiAccesoParams['urlMaster'] . '/esquemas/sync';
        Esquema::select()->update(['aud_usuario_ingreso' => '']);
        $result = $this->getDataFromMaster($url);
        if (!empty($result['data']))
            $cambio = true;

        foreach ($result['data'] as $row) {
            Esquema::updateOrCreate(
                [
                    'cod_esquema_acceso' => $row['cod_esquema_acceso'],
                    'cod_ou' => $row['cod_ou']
                ],
                [
                    'des_esquema_acceso' => $row['des_esquema_acceso'],
                    'obj_intervalos_mixtos' => $row['obj_intervalos_mixtos'],
                    'obj_intervalos_habiles' => $row['obj_intervalos_habiles'],
                    'obj_intervalos_nohabiles' => $row['obj_intervalos_nohabiles'],
                    'ind_estado' => $row['ind_estado'],
                    'fec_habilitacion_hasta' => $row['fec_habilitacion_hasta'],
                    'aud_usuario_ingreso' => $row['aud_usuario_ingreso'],
                    'aud_stm_ingreso' => $row['aud_stm_ingreso'],
                    'aud_ip_ingreso' => $row['aud_ip_ingreso'],
                    'aud_usuario_ultmod' => $row['aud_usuario_ultmod'],
                    'aud_stm_ultmod' => $row['aud_stm_ultmod'],
                    'aud_ip_ultmod' => $row['aud_ip_ultmod']
                ]
            );
        }

        if ($cambio) {
            Esquema::where('aud_usuario_ingreso', '')->delete();
            Esquemas::cleanCaches();
        }
    }

    protected function syncOU()
    {
        $cambio = false;


        $url = $this->habiAccesoParams['urlMaster'] . '/unidadesorganiz/sync';
        UnidadesOrganiz::select()->update(['aud_usuario_ingreso' => '']);
        $result = $this->getDataFromMaster($url);
        if (!empty($result['data']))
            $cambio = true;

        foreach ($result['data'] as $row) {
            UnidadesOrganiz::updateOrCreate(
                [
                    'cod_ou' => $row['cod_ou']
                ],
                [
                    'nom_ou' => $row['nom_ou'],
                    'des_ou' => $row['des_ou'],
                    'ind_ou_admin' => $row['ind_ou_admin'],
                    'aud_usuario_ingreso' => $row['aud_usuario_ingreso'],
                    'aud_stm_ingreso' => $row['aud_stm_ingreso'],
                    'aud_ip_ingreso' => $row['aud_ip_ingreso'],
                    'aud_usuario_ultmod' => $row['aud_usuario_ultmod'],
                    'aud_stm_ultmod' => $row['aud_stm_ultmod'],
                    'aud_ip_ultmod' => $row['aud_ip_ultmod']
                ]
            );
        }

        if ($cambio)
            UnidadesOrganiz::where('aud_usuario_ingreso', '')->delete();
    }

    protected function syncSectoresxOU()
    {
        $cambio = false;


        $url = $this->habiAccesoParams['urlMaster'] . '/sectoresxou/sync';
        HabiSectoresxOU::select()->update(['aud_usuario_ingreso' => '']);
        $result = $this->getDataFromMaster($url);
        if (!empty($result['data']))
            $cambio = true;

        foreach ($result['data'] as $row) {
            HabiSectoresxOU::updateOrCreate(
                [
                    'cod_sector' => $row['cod_sector'],
                    'cod_ou' => $row['cod_ou']
                ],
                [
                    'aud_usuario_ingreso' => $row['aud_usuario_ingreso'],
                    'aud_stm_ingreso' => $row['aud_stm_ingreso'],
                    'aud_ip_ingreso' => $row['aud_ip_ingreso']
                ]
            );
        }

        if ($cambio)
            HabiSectoresxOU::where('aud_usuario_ingreso', '')->delete();
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->printDebugInfo("Inicia");
        // Cache::clear();

        $this->loadConfigData();
        if (Cache::get("EstadoHabiAccesoDispo")) {
            $this->printDebugInfo("Configuración actualizada");
            $context = array(
                "msgtext" => "Configuración actualizada",
                "EstadoVal" => true,
                "EstadoDen" => "HabiAcceso",
                "EstadoColor" => "green"
            );
            Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'info',  $context);
        }
        while (1) {
            if (Cache::get(self::confVersion) != $this->daemon_conf_ver)
                $this->loadConfigData();

            if ($this->habiAccesoParams['urlMaster'] !== '') {
                $lastUpdate = $this->getLastUpdate();
                if ($lastUpdate != Cache::get('lastSync') || $lastUpdate == "0000-00-00 00:00:00") {
                    $context = array(
                        "msgtext" => "Inicio actualización permisos desde equipo maestro",
                        "EstadoVal" => false,
                        "EstadoDen" => "HabiAcceso",
                        "EstadoColor" => "yellow"
                    );
                    Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'info',  $context);

                    try {
//                        $this->syncOU();
//                        $this->syncEsquemas();
//                        $this->syncSectores();
//                        $this->syncSectoresxOU();
//                        $this->syncTemas();
                        $this->syncHabiAcceso();
                        Cache::forever("lastSync", $lastUpdate);
                        Cache::forever("EstadoHabiAccesoDispo", true);

                        $context = array(
                            "msgtext" => "Fin actualización permisos desde equipo maestro",
                            "EstadoVal" => true,
                            "EstadoDen" => "HabiAcceso",
                            "EstadoColor" => "green"
                        );
                        Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'info',  $context);


                    } catch (\Exception $e) {
                        Log::channel(self::logFileName)->error($e->getMessage(), array());

                        $context = array(
                            "msgtext" => "Error accediendo desde el equipo maestro",
                            "EstadoVal" => false,
                            "EstadoDen" => "HabiAcceso",
                            "EstadoColor" => "red"
                        );
                        
                    Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'error',  $context);
                        }


                }
            } else if (!Cache::get("EstadoHabiAccesoDispo")) {
                $this->printDebugInfo("Actualizando permisos de acceso");
                $context = array(
                    "msgtext" => "Inicio actualización permisos de acceso",
                    "EstadoVal" => false,
                    "EstadoDen" => "HabiAcceso",
                    "EstadoColor" => "red"
                );
                Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'warning',  $context);


                $this->printDebugInfo("Inicio compilación de permisos");
                HabiAccesos::checkhabiAcceso(false);
                $this->printDebugInfo("Fin compilación de permisos");

                $this->printDebugInfo("Fin actualización permisos de acceso");
                Cache::forever("EstadoHabiAccesoDispo", true);
                $context = array(
                    "msgtext" => "Fin actualización permisos de acceso",
                    "EstadoVal" => true,
                    "EstadoDen" => "HabiAcceso",
                    "EstadoColor" => "green"
                );
                Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'info',  $context);
            }
            sleep($this->habiAccesoParams['syncIntervalSeg']);
        }
    }
}
