<?php

namespace App\Traits; // *** Adjust this to match your model namespace! ***


use Auth;
use Carbon\Carbon;
use Request;

trait LibGeneral
{   
    public static function getUuid()
    {
        $uuid = hexdec(substr(hash('sha1',uniqid(rand(), false)),0,10));
        return $uuid;
    }
    
    public static function addAuditoria($tabla,$operacion)
    {
        $stm_actual = Carbon::now()->format('Y-m-d H:i:s.u');
        $cod_usuario = "anon";
        $user = Auth::user();
        if ($user)
            $cod_usuario = ($user['cod_usuario']) ? $user['cod_usuario'] : "interno";
        $ip = Request::ip();
        
        switch($operacion){
            case "M":
                $tabla->aud_stm_ultmod = $stm_actual;
                $tabla->aud_usuario_ultmod = $cod_usuario;
                $tabla->aud_ip_ultmod = $ip;
                break;
            case "A":
                $tabla->aud_stm_ingreso = $stm_actual;
                $tabla->aud_usuario_ingreso = $cod_usuario;
                $tabla->aud_ip_ingreso = $ip;
                $tabla->aud_stm_ultmod = $stm_actual;
                $tabla->aud_usuario_ultmod = $cod_usuario;
                $tabla->aud_ip_ultmod = $ip;
                break;   
            case "RL":
                $tabla->aud_stm_ingreso = $stm_actual;
                $tabla->aud_usuario_ingreso = $cod_usuario;
                $tabla->aud_ip_ingreso = $ip;
        }
        return $tabla;
    }
    
    public static function filtroQuery($query,$filtro)
    {
        if (!empty($filtro['json'])) {
            $vafiltros = $filtro['json'];
            foreach ($vafiltros as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $query->where($nombre, $operacion, $valor);
                }
            }
        }
        return $query;
    }

    public static function addDateDiff($plazo, $date = '') {

        $arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i', $plazo);
        $valor_tiempo = (isset($arr[0])) ? $arr[0] : "";
        $unidad_medida_tiempo = (isset($arr[1])) ? $arr[1] : "";
        if ($valor_tiempo == "" || $unidad_medida_tiempo == "") {
            return false;
        }

        $date = ($date == '') ? Carbon::now() : new Carbon($date);

        switch ($unidad_medida_tiempo) {
            case "H":
                $limit = $date->addHours($valor_tiempo);
                break;
            case "D":
                $limit = $date->addDays($valor_tiempo);
                break;
            case "M":
                $limit = $date->addMonths($valor_tiempo);
                break;
            case "Y":
                $limit = $date->addYears($valor_tiempo);
                break;
        }

        return $limit;
    }

    public static function filtroMatch(String $input)
    {
        $output = trim(preg_replace('!\s+!', '* +', $input));
        return "+$output*";
    }
}