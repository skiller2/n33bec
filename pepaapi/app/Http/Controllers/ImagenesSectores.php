<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\RemoteN33;
use App\ImagenSector;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;



/**
 * Description of Imagenes
 *
 * @author fpl
 */
class ImagenesSectores extends Controller {
    //put your code here
    
    public function detalle($clave) {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_sector = $clave[0][0];
        $vaimagenes = array();
        $imagen = ImagenSector::find($cod_sector);
        if ($imagen) {
            $vaimagenes[$imagen['tipo_uso']] = $imagen['blb_imagen'];
        } else {
//            return response(['error'=> __("Imagen no existe")], Response::HTTP_NOT_FOUND);
        }
        return $vaimagenes;
    }

    public function getImg($cod_sector)
    {
        $cod_sector = base64_decode($cod_sector);

        $vaResultado = ImagenSector::select('blb_imagen', 'tipo_uso')->where('cod_sector', $cod_sector)->first();
        if ($vaResultado) {
            $img_tema_b64 = $vaResultado['blb_imagen'];
            $tipo_uso = $vaResultado['tipo_uso'];
        } else {
            return response(['error' => __('Imagen no existe')], Response::HTTP_NOT_FOUND);
        }
        $header = substr($img_tema_b64,0,20);
        $image = base64_decode(substr($img_tema_b64, strpos($img_tema_b64, ",") + 1));
        $info = getimagesizefromstring($image);

//        $type="image/jpeg";
        return response($image)
            ->withHeaders(['Content-Type' => $info['mime']]);
    }

    public function getImgData($cod_tema_sector,$img_hash)
    {
        $cod_tema_sector = base64_decode($cod_tema_sector);
        $cod_tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $cod_sector=str_replace($cod_tema_local."/","",$cod_tema_sector);
        $vaRemotos = Cache::get("N33BEC_REMOTO", array());

        $vaResultado = ImagenSector::select('blb_imagen', 'tipo_uso')->where('cod_sector', $cod_sector)->first();
        if ($vaResultado) {
            $img_tema_b64 = $vaResultado['blb_imagen'];
            $tipo_uso = $vaResultado['tipo_uso'];
        } else {
            foreach ($vaRemotos as $cod_tema_origen => $remoto) {
                if (strpos($cod_tema_sector, $cod_tema_origen) === 0) {
                    $ret = RemoteN33::getRemoteImgData($remoto['url'] . "/api/v1/displaysucesos/sectorimgdata/" . base64_encode($cod_tema_sector) . "/$img_hash", 60);
                    if ($ret !== false)
                        return $ret;
                }
            }
            return response(['error' => __('Imagen no existe para sector')], Response::HTTP_NOT_FOUND);
        }

        //        $image = base64_decode(substr($img_tema_b64, strpos($img_tema_b64, ",")+1));

        return $img_tema_b64;
    }
}
