<?php

namespace App\Http\Controllers;

use App\Preferencia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use stdClass;
use function auth;
use function response;

class Preferencias extends Controller
{

    public static function getAbility($metodo)
    {
        switch ($metodo){
            default:
                return "";
        }
    }
    
    public function detalle($cod_preferencia)
    {
        $cod_usuario = auth()->user()['cod_usuario'];
        $preferencia = Preferencia::find($cod_usuario);
        
        if($cod_preferencia=="")
            return $preferencia->obj_preferencias;


        if(isset($preferencia->obj_preferencias[$cod_preferencia]))
            return $preferencia->obj_preferencias[$cod_preferencia];

             
        return "{}";
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $cod_usuario = auth()->user()['cod_usuario'];
        
        $preferencia = Preferencia::find($cod_usuario);
        if($preferencia){
            $obj_preferencias = array_merge($preferencia->obj_preferencias,$request->input('preferencias'));
            $preferencia->obj_preferencias = $obj_preferencias;            
        }
        else{
            $preferencia = new Preferencia;
            $preferencia->cod_usuario = $cod_usuario;
            $preferencia->obj_preferencias = $request->input('preferencias');
        }
        $preferencia->save();
        return response(['ok' => ''], Response::HTTP_OK);
    }
}
