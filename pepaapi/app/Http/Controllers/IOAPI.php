<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;

use App\Helpers\ConfigParametro;
use App\Helpers\TemaValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Events\TemaEvent;
use Illuminate\Support\Facades\Broadcast;

/**
 * Description of LogParser
 *
 * @author fpl
 */
class IOAPI extends Controller
{
    const config_tag = "iolast_";
    protected $tema_local;

    public function __construct()
    {
        //		parent::__construct();
        $this->tema_local = ConfigParametro::get('TEMA_LOCAL', false);
    }

    public static function getAbility($metodo)
    {
        switch ($metodo){
            case "index":
            case "store":
            case "update":
            case "delete":
            case "gridOptions":
            case "detalle":
                return "ab_config";
            default:
                return "";
        }
    }


    public function getData()
    {
        $temas = ConfigParametro::getTemas('');
        $datos_temas = array();
        foreach ($temas as $cod_tema => $detail) {
            if ($detail['ind_mostrar_en_panel']!= true) continue;
            $count = "Desc";
            $color = "";
            $nom_tema = $detail['nom_tema'];
            $value=$detail['valor_omision'];
            $direccion = "";
            $ind_manual = 0;
            if (isset($detail['ind_manual'])) 
                $ind_manual= $detail['ind_manual'];

            if (Cache::has(self::config_tag . $cod_tema))
                $value = Cache::get(self::config_tag . $cod_tema);

            $des_valor=$value;

            if (Cache::has("COUNT_" . self::config_tag . $cod_tema)) {
                $count = Cache::get("COUNT_" . self::config_tag . $cod_tema);
            }

            $res= TemaValue::get($detail,$value);
            $des_valor = $res['des_valor'];
            $direccion = $res['direccion'];
            $color = $res['color'];

            $datos_temas[$cod_tema] = array(
                "des_valor" => $des_valor,
                "nom_tema" => $nom_tema,
                "count" => $count,
                "direction" => $direccion,
                "ind_manual" =>$ind_manual,
                "color" => $color,
                "valor" => $value
            );
        }
        return $datos_temas;
    }

    public function getIOVal()
    {
//        return getData();
    }

    public function DisplayArea54($cod_tema)
    {
        $cod_tema = base64_decode($cod_tema);
        $context = Cache::get(self::config_tag .$cod_tema. "display_area54");
        if (!is_array($context))
        $context=array();
        Broadcast::driver('fast-web-socket')->broadcast(["display_area54"], 'info',  $context);
        return $context;
    }

    public function getIOData($cod_tema)
    {
        $temas = ConfigParametro::getTemas();

        if (!isset($temas[$cod_tema]))
            return "Ups $cod_tema"; 
        $detail = $temas[$cod_tema];

        $value=$detail['valor_omision'];
        if (Cache::has(self::config_tag . $cod_tema))
            $value = Cache::get(self::config_tag . $cod_tema);


        $count = Cache::get("COUNT_" . self::config_tag.$cod_tema);
        $nom_tema = $detail['nom_tema'];

        $res= TemaValue::get($detail,$value);
        $des_valor = $res['des_valor'];
        $direccion = $res['direccion'];
        $color = $res['color'];

        return array("cod_tema" => $cod_tema, "valor" => $value, "des_valor" => $des_valor, "count" => $count, "button" => "", "nom_tema" => $nom_tema, "color" => $color);
    }

    public function getIOExtVal($id_disp_origen, $io_name)
    {
        return $this->getIOData($id_disp_origen, $io_name);
    }

    public function resetCount($cod_tema)
    {
        Cache::forever("COUNT_" . self::config_tag . $cod_tema, 0);
        return $cod_tema;
    }

}
