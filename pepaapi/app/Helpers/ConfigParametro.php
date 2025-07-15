<?php

namespace App\Helpers;

use App\Dispositivo;
use App\Esquema;
use App\Sector;
use App\Parametro;
use App\Tema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/**
 * Somehelper Helpers
 * Helpers site wide
 */
class ConfigParametro
{

    const config_tag = "config_";

    public static function get($key, $ind_json = false, $default_value = "")
    {
        $config_tag = self::config_tag;
        $value = $default_value;
        if (Cache::has($config_tag . $key)) {
            $value = Cache::get($config_tag . $key);
        } else {
            $setting = Parametro::find($key);
            if ($setting) {
                $value = $setting->val_parametro;
                Cache::forever($config_tag . $key, $value);
            }
        }
        if ($ind_json) {
            if (json_decode($value, true))
                $value = json_decode($value, true);
            else
                $value = array();
        }
        return $value;
    }

    public static function clear($key)
    {
        $config_tag = self::config_tag;
        Cache::forget($config_tag . $key);
    }

    public static function getLocalIOs()
    {
        $localIOs = array();
        if (Cache::has("LOCALIOS")) {
            $localIOs = Cache::get("LOCALIOS");
        } else {
            $cod_tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));

            $resultado = Tema::select('cod_tema', 'nom_tema', 'json_parametros', 'cod_tipo_uso')
                ->where('cod_tema', 'LIKE', $cod_tema_local . "%")
                ->where('ind_activo', 1)
                ->where('cod_tipo_uso', 'DIN')
                ->orwhere('cod_tipo_uso', 'DINEXT')
                ->orWhere('cod_tipo_uso', 'DOUT')
                ->get();
            foreach ($resultado as $row) {
                $params = (is_array($row['json_parametros'])) ? $row['json_parametros'] : array();
                $cod_tipo_uso = $row['cod_tipo_uso'];
                $cod_tema = $row['cod_tema'];
                $nom_tema = $row['nom_tema'];
                $io = (isset($params['io'])) ? $params['io'] : null;
                if (!$io) {
                    continue;
                }

                $localIOs[$io] = array(
                    "cod_tipo_uso" => $cod_tipo_uso,
                    "cod_tema" => $cod_tema,
                    "nom_tema" => $nom_tema,
                    "val_0" => (isset($params['val_0'])) ? $params['val_0'] : "0",
                    "val_1" => (isset($params['val_1'])) ? $params['val_1'] : "1",
                    "count" => (isset($params['count'])) ? $params['count'] : "false",
                    "color_val_0" => (isset($params['color_val_0'])) ? $params['color_val_0'] : "",
                    "color_val_1" => (isset($params['color_val_1'])) ? $params['color_val_1'] : "",
                );
            }

            Cache::forever("LOCALIOS", $localIOs);
        }
        return $localIOs;
    }


    public static function getEsquemas()
    {
        if (Cache::has("ESQUEMAS")) {
            $esquemas = Cache::get("ESQUEMAS");
        } else {
            $esquemas = array();
            $esq = Esquema::select()
                ->where('ind_estado', '=', '1')
                ->where('fec_habilitacion_hasta', '>', Carbon::now())
                ->get();
            foreach ($esq as $row) {
                $esquemas[$row['cod_esquema_acceso']] = array(
                    "int_hab" => $row['obj_intervalos_habiles'],
                    "int_nohab" => $row['obj_intervalos_nohabiles'],
                    "int_mix" => $row['obj_intervalos_mixtos'],
                    "fec_habilitacion_hasta" => $row['fec_habilitacion_hasta']
                );
            }
            if (!empty($esquemas))
                Cache::forever("ESQUEMAS", $esquemas);
        }
        return $esquemas;
    }

    public static function getCantTemasSector()
    {
        $cod_tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));

        if (Cache::has("TEMASSECTORES")) {
            $sectores = Cache::get("TEMASSECTORES");
        } else {
            $temassectores = array();
            $query =  Sector::select('maesSectores.cod_sector', 'maesSectores.cod_sector_padre','maesSectores.cod_referencia', 'maesSectores.nom_sector', 'maesSectores.ind_permanencia', 'maesSectores.max_cant_personas', 'maesSectores.obj_urls_videos', DB::raw('COUNT(maesTemas.cod_tema) as cant_cod_tema'))
            ->leftJoin('maesTemas', 'maesTemas.cod_sector', '=', 'maesSectores.cod_sector')
            ->groupBy('maesSectores.cod_sector', 'maesSectores.cod_sector_padre','maesSectores.cod_referencia', 'maesSectores.nom_sector', 'maesSectores.ind_permanencia', 'maesSectores.max_cant_personas', 'maesSectores.obj_urls_videos')
            ->get();
            foreach ($query as $row) {
                $temassectores[$row['cod_sector']] = array(
                    "nom_sector" => $row['nom_sector'],
                    "cod_sector_padre" => $row['cod_sector_padre'],
                    "cod_tema_sector" => $cod_tema_local."/".$row['cod_sector'],
                    "cod_referencia" => $row['cod_referencia'],
                    "ind_permanencia" => ($row['ind_permanencia'] == 1) ? 1 : 0,
                    "obj_urls_videos" => $row['obj_urls_videos'],
                    "familia" => array(),
                    "max_cant_personas" => $row['max_cant_personas'],
                    "cant_cod_tema" => $row['cant_cod_tema'],
                );
            }

            if (!empty($temassectores))
                Cache::forever("TEMASSECTORES", $temassectores);
        }
        return $temassectores;
    }



    public static function getSectores()
    {
        $cod_tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));

        if (Cache::has("SECTORES")) {
            $sectores = Cache::get("SECTORES");
        } else {
        /*
            function getPadre($sectores, $cod_sector)
            {
                return $sectores['$cod_sector']['cod_sector_padre'];
            }
          */
            $sectores = array();
            $query =  Sector::select('maesSectores.cod_sector', 'maesSectores.cod_sector_padre','maesSectores.cod_referencia', 'maesSectores.nom_sector', 'maesSectores.ind_permanencia', 'maesSectores.max_cant_personas', 'maesSectores.obj_urls_videos', DB::raw('COUNT(maesTemas.cod_tema) as cant_cod_tema'))
            ->leftJoin('maesTemas', 'maesTemas.cod_sector', '=', 'maesSectores.cod_sector')
            ->groupBy('maesSectores.cod_sector', 'maesSectores.cod_sector_padre','maesSectores.cod_referencia', 'maesSectores.nom_sector', 'maesSectores.ind_permanencia', 'maesSectores.max_cant_personas', 'maesSectores.obj_urls_videos')
            ->get();
            foreach ($query as $row) {
                $sectores[$row['cod_sector']] = array(
                    "nom_sector" => $row['nom_sector'],
                    "cod_sector_padre" => $row['cod_sector_padre'],
                    "cod_tema_sector" => $cod_tema_local."/".$row['cod_sector'],
                    "cod_referencia" => $row['cod_referencia'],
                    "ind_permanencia" => ($row['ind_permanencia'] == 1) ? 1 : 0,
                    "obj_urls_videos" => $row['obj_urls_videos'],
                    "familia" => array(),
                    "max_cant_personas" => $row['max_cant_personas'],
                    "cant_cod_tema" => $row['cant_cod_tema'],
                );
            }

            foreach ($sectores as $cod_sector_orig => $vasector) {
                $cod_sector = $cod_sector_orig;
                while (true) {
                    $cod_sector_padre = (isset($sectores[$cod_sector])) ? $sectores[$cod_sector]['cod_sector_padre'] : "";

                    if ($cod_sector_padre == "" || $cod_sector_padre == $cod_sector)
                        break;

                    $sectores[$cod_sector_orig]['familia'][] = $cod_sector_padre;
                    $cod_sector = $cod_sector_padre;
                }
            }

            if (!empty($sectores))
                Cache::forever("SECTORES", $sectores);
        }
        return $sectores;
    }


    public static function getTemas($cod_tipo_uso_busq = null)
    {
        if (!$cod_tipo_uso_busq) {
            if (Cache::has("TEMAS_CACHE")) {
                $vatemas = Cache::get("TEMAS_CACHE");
                return $vatemas;
            }
        }


        $query = Tema::select()->where('ind_activo', 1);
        if ($cod_tipo_uso_busq) {
            $query->where('cod_tipo_uso', $cod_tipo_uso_busq);
        }
        $respuesta = $query->get();
        $vatemas = array();

        foreach ($respuesta as $row) {
            $params = $row['json_parametros'];
            $subtemas = $row['json_subtemas'];
            $cod_tema = strtolower($row['cod_tema']);
            $nom_tema = $row['nom_tema'];
            $cod_tipo_uso = $row['cod_tipo_uso'];
            $cod_sector = $row['cod_sector'];
            $cod_clase = $row['cod_clase'];
            $url_envio = $row['url_envio'];
            $des_ubicacion = $row['des_ubicacion'];
            $ind_mostrar_en_panel = $row['ind_mostrar_en_panel'];
            $ind_registra_evento = $row['ind_registra_evento'];
            $ind_display_evento = $row['ind_display_evento'];
            $ind_notifica_evento = $row['ind_notifica_evento'];

            $ind_manual = 0;
            $json_posicion_img = $row['json_posicion_img'];

            switch ($cod_tipo_uso) {
                case "LECTOR":
                    $tipo_credencial = (isset($params['tipo_credencial'])) ? $params['tipo_credencial'] : "";
                    $ind_retencion = (isset($params['ind_retencion'])) ? $params['ind_retencion'] : "";
                    $ind_movimiento = (isset($params['ind_movimiento'])) ? $params['ind_movimiento'] : "";
                    $tipo_habilitaciones = (isset($params['tipo_habilitaciones'])) ? array_combine($params['tipo_habilitaciones'], $params['tipo_habilitaciones']) : array();
                    $ind_separa_facility_code = (isset($params['ind_separa_facility_code'])) ? $params['ind_separa_facility_code'] : "0";
                    $ind_rex = (isset($params['ind_rex'])) ? $params['ind_rex'] : "0";
                    $valor_omision = 0;
                    switch ($ind_movimiento) {
                        case "I":
                            $des_movimiento = "Ingreso";
                            break;
                        case "E":
                            $des_movimiento = "Egreso";
                            break;
                        case "L":
                            $des_movimiento = "Lectura";
                            break;
                        default:
                            $des_movimiento = "Desconocido";
                            break;
                    }
                    $res_error = array(
                        "rele1" => (isset($params['res_error_rele1'])) ? $params['res_error_rele1'] : "0",
                        "rele2" => (isset($params['res_error_rele2'])) ? $params['res_error_rele2'] : "0",
                        "rele3" => (isset($params['res_error_rele3'])) ? $params['res_error_rele3'] : "0",
                        "buzzer" => (isset($params['res_error_buzzer'])) ? $params['res_error_buzzer'] : "0",
                        "led" => (isset($params['res_error_led'])) ? $params['res_error_led'] : "0",
                    );
                    $res_ok = array(
                        "rele1" => (isset($params['res_ok_rele1'])) ? $params['res_ok_rele1'] : "0",
                        "rele2" => (isset($params['res_ok_rele2'])) ? $params['res_ok_rele2'] : "0",
                        "rele3" => (isset($params['res_ok_rele3'])) ? $params['res_ok_rele3'] : "0",
                        "buzzer" => (isset($params['res_ok_buzzer'])) ? $params['res_ok_buzzer'] : "0",
                        "led" => (isset($params['res_ok_led'])) ? $params['res_ok_led'] : "0",
                    );
                    $vatemas[$cod_tema] = array(
                        "tipo_credencial" => $tipo_credencial, "ind_retencion" => $ind_retencion,
                        "ind_movimiento" => $ind_movimiento, "tipo_habilitaciones" => $tipo_habilitaciones, "valor_omision" => $valor_omision,
                        "ind_separa_facility_code" => $ind_separa_facility_code, "ind_rex" => $ind_rex, "des_movimiento" => $des_movimiento,
                        "res_error" => $res_error, "res_ok" => $res_ok
                    );
                    break;
                case "AIN":
                    $count = (isset($params['count'])) ? $params['count'] : "false";
                    $color_val = (isset($params['color_val'])) ? $params['color_val'] : "";
                    $measure_unit = (isset($params['measure_unit'])) ? $params['measure_unit'] : "";
                    $valor_omision = 0;
                    $ind_manual = 0;
                    $vatemas[$cod_tema] = array(
                        "measure_unit" => $measure_unit, "count" => $count, "valor_omision" => $valor_omision,
                        "color_val" => $color_val, "ind_manual" => $ind_manual
                    );
                    break;
                case "DINEXT":
                    $val_FA = (isset($params['val_FA'])) ? trim($params['val_FA']) : "NODEF";
                    $val_AL = (isset($params['val_AL'])) ? trim($params['val_AL']) : "NODEF";
                    $val_NO = (isset($params['val_NO'])) ? trim($params['val_NO']) : "NODEF";
                    $val_PA = (isset($params['val_PA'])) ? trim($params['val_PA']) : "NODEF";
                    $val_AT = (isset($params['val_AT'])) ? trim($params['val_AT']) : "NODEF";
                    $val_DE = (isset($params['val_DE'])) ? trim($params['val_DE']) : "NODEF";
                    $val_EV = (isset($params['val_EV'])) ? trim($params['val_EV']) : "NODEF";
                    $val_IG = (isset($params['val_IG'])) ? trim($params['val_IG']) : "NODEF";

                    $count = (isset($params['count'])) ? $params['count'] : "false";
                    $ind_manual = (isset($params['ind_manual'])) ? $params['ind_manual'] : "0";
                    $auto_reset = (isset($params['auto_reset'])) ? $params['auto_reset'] : "0";
                    $valor_omision = (isset($params['valor_omision'])) ? $params['valor_omision'] : "0";
                    $val_delay_sec_AL = (isset($params['val_delay_sec_AL'])) ? $params['val_delay_sec_AL'] : "0";
                    $bus_id        = (isset($params['bus_id'])) ? $params['bus_id'] : "";

                    $vatemas[$cod_tema] = array(
                        "val_FA" => $val_FA, "val_AL" => $val_AL, "val_NO" => $val_NO, "val_PA" => $val_PA, "val_AT" => $val_AT, "val_DE" => $val_DE, "val_EV"=>$val_EV, "val_IG"=>$val_IG,
                        "val_delay_sec_AL" => $val_delay_sec_AL,
                        "count" => $count, "ind_manual" => $ind_manual, "auto_reset" => $auto_reset, "valor_omision" => $valor_omision,
                        "bus_id" => $bus_id 

                    );
                    break;
                case "DIN":
                    $val_0 = (isset($params['val_0'])) ? $params['val_0'] : "0";
                    $val_1 = (isset($params['val_1'])) ? $params['val_1'] : "1";
                    $count = (isset($params['count'])) ? $params['count'] : "false";
                    $ind_manual = (isset($params['ind_manual'])) ? $params['ind_manual'] : "0";
                    $valor_omision = (isset($params['valor_omision'])) ? $params['valor_omision'] : "0";

                    $color_val_0 = (isset($params['color_val_0'])) ? $params['color_val_0'] : "";
                    $color_val_1 = (isset($params['color_val_1'])) ? $params['color_val_1'] : "";
                    $bus_id        = (isset($params['bus_id'])) ? $params['bus_id'] : "";

                    $vatemas[$cod_tema] = array(
                        "val_0" => $val_0, "val_1" => $val_1, "count" => $count, "valor_omision" => $valor_omision,
                        "color_val_0" => $color_val_0, "color_val_1" => $color_val_1, "ind_manual" => $ind_manual,
                        "bus_id" => $bus_id 
                    );
                    break;
                case "DOUT":
                    $val_FA = (isset($params['val_FA'])) ? trim($params['val_FA']) : "NODEF";
                    $val_AL = (isset($params['val_AL'])) ? trim($params['val_AL']) : "NODEF";
                    $val_NO = (isset($params['val_NO'])) ? trim($params['val_NO']) : "NODEF";
                    $val_PA = (isset($params['val_PA'])) ? trim($params['val_PA']) : "NODEF";
                    $val_AT = (isset($params['val_AT'])) ? trim($params['val_AT']) : "NODEF";
                    $val_DE = (isset($params['val_DE'])) ? trim($params['val_DE']) : "NODEF";
                    $val_EV = (isset($params['val_EV'])) ? trim($params['val_EV']) : "NODEF";
                    $val_IG = (isset($params['val_IG'])) ? trim($params['val_IG']) : "NODEF";
                                        
                    $count = (isset($params['count'])) ? $params['count'] : "false";

                    $io          = (isset($params['io'])) ? $params['io'] : "";
                    $action_type = (isset($params['action_type'])) ? $params['action_type'] : "local";
                    $valor_omision = (isset($params['valor_omision'])) ? $params['valor_omision'] : "0";

                    $bus_id      = (isset($params['bus_id'])) ? $params['bus_id'] : "";
                    $ind_manual  = 1;
                    $vatemas[$cod_tema] = array(
                        "val_FA" => $val_FA, "val_AL" => $val_AL, "val_NO" => $val_NO, "val_PA" => $val_PA, "val_AT" => $val_AT, "val_DE" => $val_DE,"val_EV"=>$val_EV, "val_IG"=>$val_IG,
                        "count" => $count, "ind_manual" => $ind_manual, "valor_omision" => $valor_omision,
                        "io" => $io, "accion_type" => $action_type, "bus_id" => $bus_id
                    );
                    break;
                case "COMUNIC":
                    $url_check = (isset($params['url_check'])) ? $params['url_check'] : "";
                    $intervalo_seg = (isset($params['intervalo_seg'])) ? $params['intervalo_seg'] : "4";

                    $val_FA = (isset($params['val_FA'])) ? trim($params['val_FA']) : "NODEF";
                    $val_AL = (isset($params['val_AL'])) ? trim($params['val_AL']) : "NODEF";
                    $val_NO = (isset($params['val_NO'])) ? trim($params['val_NO']) : "NODEF";
                    $val_PA = (isset($params['val_PA'])) ? trim($params['val_PA']) : "NODEF";
                    $val_AT = (isset($params['val_AT'])) ? trim($params['val_AT']) : "NODEF";
                    $val_DE = (isset($params['val_DE'])) ? trim($params['val_DE']) : "NODEF";
                    $val_EV = (isset($params['val_EV'])) ? trim($params['val_EV']) : "NODEF";
                    $val_IG = (isset($params['val_IG'])) ? trim($params['val_IG']) : "NODEF";

                    $ind_manual    = (isset($params['ind_manual'])) ? $params['ind_manual'] : "0";
                    $bus_id        = (isset($params['bus_id'])) ? $params['bus_id'] : "";
                    $io            = (isset($params['io'])) ? $params['io'] : "";
                    $action_type   = (isset($params['action_type'])) ? $params['action_type'] : "local";
                    $auto_reset    = (isset($params['auto_reset'])) ? $params['auto_reset'] : "0";
                    $valor_omision = (isset($params['valor_omision'])) ? $params['valor_omision'] : "0";

                    $count = (isset($params['count'])) ? $params['count'] : "false";
                    $vatemas[$cod_tema] = array(
                        "url_check" => $url_check, "intervalo_seg" => $intervalo_seg, "count" => $count,
                        "val_FA" => $val_FA, "val_AL" => $val_AL, "val_NO" => $val_NO, "val_PA" => $val_PA, "val_AT" => $val_AT, "val_DE" => $val_DE,"val_EV"=>$val_EV, "val_IG"=>$val_IG,
                        "ind_manual" => $ind_manual, "io" => $io, "accion_type" => $action_type, "bus_id" => $bus_id, "auto_reset" => $auto_reset, "valor_omision" => $valor_omision
                    );
                    break;
                case "AIN":
                    $vatemas[$cod_tema] = array();
                    break;
                case "AOUT":
                    $vatemas[$cod_tema] = array();
                    break;
                case "SUCESO":
                    $des_destinatarios = (isset($params['des_destinatarios'])) ? $params['des_destinatarios'] : array();
                    $des_template_mail = (isset($params['des_template_mail'])) ? $params['des_template_mail'] : "";
                    $obj_urls_videos = (isset($params['obj_urls_videos'])) ? $params['obj_urls_videos'] : array();

                    $obj_intervalos_habiles = (isset($params['obj_intervalos_habiles'])) ? $params['obj_intervalos_habiles'] : array();
                    $obj_intervalos_nohabiles = (isset($params['obj_intervalos_nohabiles'])) ? $params['obj_intervalos_nohabiles'] : array();
                    $obj_intervalos_mixtos = (isset($params['obj_intervalos_mixtos'])) ? $params['obj_intervalos_mixtos'] : array();

                    $ind_activa_audio_alarma = (isset($params['ind_activa_audio_alarma'])) ? $params['ind_activa_audio_alarma'] : "0";
                    $ind_activa_audio_prealarma = (isset($params['ind_activa_audio_prealarma'])) ? $params['ind_activa_audio_prealarma'] : "0";
                    $ind_activa_audio_alarmatec = (isset($params['ind_activa_audio_alarmatec'])) ? $params['ind_activa_audio_alarmatec'] : "0";
                    $ind_activa_audio_falla = (isset($params['ind_activa_audio_falla'])) ? $params['ind_activa_audio_falla'] : "0";

                    $valor_omision = (isset($params['valor_omision'])) ? $params['valor_omision'] : "0";

                    $cond_alarma = (isset($params['cond_alarma'])) ? $params['cond_alarma'] : "";
                    $cond_prealarma = (isset($params['cond_prealarma'])) ? $params['cond_prealarma'] : "";
                    $cond_falla = (isset($params['cond_falla'])) ? $params['cond_falla'] : "";
                    $cond_alarmatec = (isset($params['cond_alarmatec'])) ? $params['cond_alarmatec'] : "";
                    $cond_normal = (isset($params['cond_normal'])) ? $params['cond_normal'] : "";

                    $acciones_alarma = (isset($params['acciones_alarma'])) ? $params['acciones_alarma'] : "";
                    $acciones_prealarma = (isset($params['acciones_prealarma'])) ? $params['acciones_prealarma'] : "";
                    $acciones_alarmatec = (isset($params['acciones_alarmatec'])) ? $params['acciones_alarmatec'] : "";
                    $acciones_falla = (isset($params['acciones_falla'])) ? $params['acciones_falla'] : "";
                    $acciones_normal = (isset($params['acciones_normal'])) ? $params['acciones_normal'] : "";
                    $subtemas = (is_array($subtemas)) ? $subtemas : array();

                    $vatemas[$cod_tema] = array(
                        "acciones_alarma" => $acciones_alarma, "des_destinatarios" => $des_destinatarios,
                        "des_template_mail" => $des_template_mail,
                        "ind_activa_audio_alarma" => $ind_activa_audio_alarma, "ind_activa_audio_prealarma" => $ind_activa_audio_prealarma,
                        "ind_activa_audio_alarmatec" => $ind_activa_audio_alarmatec, "ind_activa_audio_falla" => $ind_activa_audio_falla,
                        "obj_urls_videos" => $obj_urls_videos, "obj_intervalos_habiles" => $obj_intervalos_habiles,
                        "obj_intervalos_nohabiles" => $obj_intervalos_nohabiles, "obj_intervalos_mixtos" => $obj_intervalos_mixtos,
                        "cond_alarma" => $cond_alarma, "cond_alarmatec" => $cond_alarmatec, "cond_prealarma" => $cond_prealarma, "cond_falla" => $cond_falla, "cond_normal" => $cond_normal,
                        "acciones_prealarma" => $acciones_prealarma, "acciones_alarmatec" => $acciones_alarmatec, "acciones_falla" => $acciones_falla, "acciones_normal" => $acciones_normal,
                        "valor_omision" => $valor_omision,
                        "subtemas" => $subtemas
                    );
                    break;
            }
            $vatemas[$cod_tema]["hashkey"] = bin2hex(mhash(MHASH_WHIRLPOOL, $cod_tema));
            $vatemas[$cod_tema]["cod_tipo_uso"] = $cod_tipo_uso;
            $vatemas[$cod_tema]["nom_tema"] = $nom_tema;
            $vatemas[$cod_tema]["url_envio"] = $url_envio;
            $vatemas[$cod_tema]["cod_sector"] = $cod_sector;
            $vatemas[$cod_tema]["cod_clase"] = $cod_clase;
            $vatemas[$cod_tema]["des_ubicacion"] = $des_ubicacion;
            $vatemas[$cod_tema]["ind_mostrar_en_panel"] = $ind_mostrar_en_panel;
            $vatemas[$cod_tema]["ind_registra_evento"] = $ind_registra_evento;
            $vatemas[$cod_tema]["ind_display_evento"] = $ind_display_evento;
            $vatemas[$cod_tema]["ind_notifica_evento"] = $ind_notifica_evento;
            $vatemas[$cod_tema]["json_posicion_img"] = $json_posicion_img;
        }

        if (!$cod_tipo_uso_busq)
            Cache::forever("TEMAS_CACHE", $vatemas);

        return $vatemas;
    }
}
