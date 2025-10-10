<?php

namespace App\Helpers;

/**
 * Somehelper Helpers
 * Helpers site wide
 */
class TemaValue {

    const config_tag = "config_";

    static function getDetalleDINEXT($detail,$valor){
        $val_NO=(isset($detail['val_NO']))? $detail['val_NO'] : "NODEF";
        $val_AL=(isset($detail['val_AL']))? $detail['val_AL'] : "NODEF";
        $val_PA=(isset($detail['val_PA']))? $detail['val_PA'] : "NODEF";
        $val_AT=(isset($detail['val_AT']))? $detail['val_AT'] : "NODEF";
        $val_DE=(isset($detail['val_DE']))? $detail['val_DE'] : "NODEF";
        $val_FA=(isset($detail['val_FA']))? $detail['val_FA'] : "NODEF";
        $val_EV=(isset($detail['val_EV']))? $detail['val_EV'] : "NODEF";
        $val_IG=(isset($detail['val_IG']))? $detail['val_IG'] : "NODEF";

        if ($val_NO == "*"){
            $des_valor = __("Normal");
            $color = "cs-alert-NO";
            $tipo_evento = "NO";
        } else
        if ($val_EV == "*") {
            $des_valor = __("Evento");
            $color = "cs-alert-EV";
            $tipo_evento = "EV";
        }else
        if ($val_IG == "*") {
            $des_valor = __("Ignorado");
            $color = "";
            $tipo_evento = "IG";
        } else
        if (strcmp($valor,$val_NO)==0){
            $des_valor = __("Normal");
            $color = "cs-alert-NO";
            $tipo_evento = "NO";
        } else
        if (strcmp($valor,$val_AL)==0){
            $des_valor = __("Alarma");
            $color = "cs-alert-AL";
            $tipo_evento = "AL";
        } else
        if (strcmp($valor,$val_PA)==0){
            $des_valor = __("PreAlarma");
            $color = "cs-alert-PA";
            $tipo_evento = "PA";
        } else
        if (strcmp($valor,$val_AT)==0){
            $des_valor = __("Alarma Técnica");
            $color = "cs-alert-AT";
            $tipo_evento = "AT";
        } else
        if (strcmp($valor,$val_DE)==0){
            $des_valor = __("Desconexión");
            $color = "cs-alert-DE";
            $tipo_evento = "DE";
        } else
        if (strcmp($valor,$val_FA)==0){
            $des_valor = __("Falla");
            $color = "cs-alert-FA";
            $tipo_evento = "FA";
        } else
        if (strcmp($valor,$val_EV)==0) {
            $des_valor = __("Evento");
            $color = "cs-alert-EV";
            $tipo_evento = "EV";
        } else
        if (strcmp($valor,$val_IG)==0) {
            $des_valor = __("Ignorado");
            $color = "";
            $tipo_evento = "IG";
        }

        else {
            $des_valor = __("Falla");
            $color = "cs-alert-FA";
            $tipo_evento = "FA";
        } 
        return array("des_valor"=>$des_valor, "color"=>$color, "tipo_evento"=>$tipo_evento);
    }

    public static function get($detail,$value) {
        $des_valor="";
        $direccion="";
        $nom_tema = $detail['nom_tema'];
        $color = "";
        $tipo_evento = "";
        switch ($detail['cod_tipo_uso']) {
            case 'COMUNIC':
            case 'DINEXT':
                $det=SELF::getDetalleDINEXT($detail,$value);
                $valor_digital = $value;
                $des_valor = $det['des_valor'];
                $tipo_evento = $det['tipo_evento'];
                $direccion = "IN";
                $color = $det['color'];;
            break;
            case 'DIN':
                $valor_digital = ($value >= 1) ? "1" : "0";
                $des_valor = $detail['val_' . $valor_digital];
                $direccion = "IN";
                $color = $detail['color_val_' . $valor_digital];
                $tipo_evento = "";
                break;
            case 'DOUT':
                $det=SELF::getDetalleDINEXT($detail,$value);
                $valor_digital = $value;
                $des_valor = $det['des_valor'];
                $tipo_evento = $det['tipo_evento'];
                $direccion = "IN";
                $color = $det['color'];;
                break;
            case 'SUCESO':
                $direccion = "IN";
                $des_valor = $value;
                $tipo_evento = "";
                break;
            case 'LECTOR':
                $direccion = "IN";
                $des_valor = $value;
                $tipo_evento = "";
                break;
            default:
                break;
        }
        return array('direccion'=>$direccion,'des_valor'=>$des_valor,'nom_tema'=>$nom_tema, 'color'=>$color, 'tipo_evento'=>$tipo_evento);
    }
}