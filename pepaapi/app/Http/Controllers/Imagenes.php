<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;

use App\Imagen;
use Illuminate\Http\Response;

/**
 * Description of Imagenes
 *
 * @author fpl
 */
class Imagenes extends Controller {
    //put your code here
    
    public function detalle($tipo_imagen,$clave) {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_imagen = $clave[0][0];

        $imagen = Imagen::find($cod_imagen);
        
        switch ($tipo_imagen) {
            case 'persona':
                $campo="img_persona";
                break;

            case 'documento':
                $campo="img_documento";
                break;

            default:
                return response(['error' => "Tipo imagen $tipo_imagen desconocido"], Response::HTTP_NOT_FOUND);
                break;
        }

        if ( !$imagen || empty($imagen[$campo]) )
            return response(['error' => "Imagen no localizada tipo $tipo_imagen"], Response::HTTP_NOT_FOUND);


        switch ($tipo_imagen) {
            case 'persona':
                return $imagen['img_persona'];
                # code...
                break;
            case 'documento':
                return $imagen['img_documento'];
                break;

            default:
                # code...
                break;
        }
        
        return $imagen;
    }
}
