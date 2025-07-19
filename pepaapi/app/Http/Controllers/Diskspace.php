<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;
use App\Helpers\ConfigParametro;

/**
 * Description of LogParser
 *
 * @author fpl
 */
class Diskspace extends Controller 
{
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

    public function getData($selected_disk)
    {
        $disco[0] = array("x"=> "Utilizado","y"=>0);
        $disco[1] = array("x"=> "Disponible","y"=>0);
        
        $selected_disk = json_decode($selected_disk,true);        
        $selected_disk = ($selected_disk != "") ? $selected_disk : "/";
        
        $si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
        $base = 1024;        
        
        $bytes_free = disk_free_space($selected_disk);
        $class = min((int)log($bytes_free , $base) , count($si_prefix) - 1);
        $valor_free = sprintf('%1.2f' , $bytes_free / pow($base,$class));        
        $disco[1] = array("x"=>__("Disponible (:VALOR_FREE :CLASS)",['VALOR_FREE'=>$valor_free,'CLASS'=>$si_prefix[$class]]),"y"=>$bytes_free);
        
    
        $bytes_utilizado = disk_total_space($selected_disk) - $bytes_free;
        $class = min((int)log($bytes_utilizado , $base) , count($si_prefix) - 1);
        $valor_utilizado = sprintf('%1.2f' , $bytes_utilizado / pow($base,$class));        
        $disco[0] = array("x"=>__("Utilizado (:VALOR_UTILIZADO :CLASS)",['VALOR_UTILIZADO'=>$valor_utilizado,'CLASS'=>$si_prefix[$class]]),"y"=>$bytes_utilizado);
        
        return array("disco"=>$disco);
    }    
}
