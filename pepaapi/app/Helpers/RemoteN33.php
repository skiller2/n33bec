<?php

namespace App\Helpers;

use App\Dispositivo;
use App\Esquema;
use App\Sector;
use App\Parametro;
use App\Tema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Somehelper Helpers
 * Helpers site wide
 */
class RemoteN33
{

    const config_tag = "config_";

    public static function getRemoteImgData($url, $timeout)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen("")
        ));
        //                    curl_setopt($ch, CURLOPT_POST, 0);
        //                    curl_setopt($ch, CURLOPT_POSTFIELDS, $evento);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        //                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $ret = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        //file_put_contents('C:/temp/salida2.txt',"$http_code $url $ret \n",FILE_APPEND);                

        if ($http_code == 200) {
            return $ret;
        } else if ($http_code == 404) {
            return response($ret, $http_code);
        }

        return false;
    }

    public static function postRemoteData($url, $postFields)
    {
        $ch = curl_init($url);
        /*
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen("")
                ));
                */
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $ret = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            return json_decode($ret, true);
        } else if ($http_code == 404 || $http_code == 409) {
            return response($ret, $http_code);
        }
        return false;
    }

    public static function getRemoteData($url, $timeout)
    {

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen("")
        ));
        //                    curl_setopt($ch, CURLOPT_POST, 0);
        //                    curl_setopt($ch, CURLOPT_POSTFIELDS, $evento);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $ret = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        //file_put_contents('C:/temp/salida.txt',"$http_code $url $ret \n",FILE_APPEND);                
        if ($http_code == 200) {
            return json_decode($ret, true);
        } else if ($http_code == 404) {
            return response($ret, $http_code);
        }
        return false;
    }
}
