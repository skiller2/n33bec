<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

use App\ImagenTema;
use App\Helpers\RemoteN33;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;

/**
 * Description of Imagenes
 *
 * @author fpl
 */
class ImagenesTemas extends Controller
{
    //put your code here
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
    
    public function detalle($clave)
    {
        $clave = json_decode(base64_decode($clave), true);
        $cod_tema = $clave[0][0];
        $vaimagenes = array();
        $imagen = ImagenTema::find($cod_tema);
        if ($imagen) {
            $vaimagenes[$imagen['tipo_uso']] = $imagen['img_tema'];
        } else {
            //            return response(['error' => 'Imagen no existe'], Response::HTTP_NOT_FOUND);
        }
        return $vaimagenes;
    }

    public function getImg($cod_tema)
    {
        $cod_tema = base64_decode($cod_tema);

        $vaResultado = ImagenTema::select('img_tema', 'tipo_uso')->where('cod_tema', $cod_tema)->first();
        if ($vaResultado) {
            $img_tema_b64 = $vaResultado['img_tema'];
            $tipo_uso = $vaResultado['tipo_uso'];
        } else {
            return response(['error' => 'Imagen no existe'], Response::HTTP_NOT_FOUND);
        }
        $header = substr($img_tema_b64, 0, 20);
        $image = base64_decode(substr($img_tema_b64, strpos($img_tema_b64, ",") + 1));
        $info = getimagesizefromstring($image);

        //        $type="image/jpeg";
        return response($image)
            ->withHeaders(['Content-Type' => $info['mime']]);
    }

    public function getImgData($cod_tema, $img_hash)
    {

        $cod_tema = base64_decode($cod_tema);
        $vaRemotos = Cache::get("N33BEC_REMOTO", array());

        $vaResultado = ImagenTema::select('img_tema', 'tipo_uso')->where('cod_tema', $cod_tema)->first();

        if ($vaResultado) {
            $img_tema_b64 = $vaResultado['img_tema'];
            $tipo_uso = $vaResultado['tipo_uso'];
        } else {
            foreach ($vaRemotos as $cod_tema_origen => $remoto) {
                if (strpos($cod_tema, $cod_tema_origen) === 0) {
                    $ret = RemoteN33::getRemoteImgData($remoto['url'] . "/api/v1/displaysucesos/temaimgdata/" . base64_encode($cod_tema) . "/$img_hash", 60);
                    if ($ret !== false)
                        return $ret;
                }
            }
            return response(['error' => 'Imagen no existe'], Response::HTTP_NOT_FOUND);
        }

        //        $image = base64_decode(substr($img_tema_b64, strpos($img_tema_b64, ",")+1));

        return $img_tema_b64;
    }
}
