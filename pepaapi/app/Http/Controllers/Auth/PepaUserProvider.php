<?php

namespace App\Http\Controllers\Auth;

use App\Usuario;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;


class PepaUserProvider implements UserProvider {

    /**
    * Retrieve a user by their unique identifier.
    *
    * @param  mixed $cod_usuario
    * @return Authenticatable|null
    */
    public function retrieveById($cod_usuario)
    {
        //Log::write('acceso', 'retrieveById:',['user'=>$cod_usuario]);
        if (empty($cod_usuario)) return null;
		$luser = Cache::get('user_'.$cod_usuario);
		
		
		
        if($luser)
            return $luser;

        $qry = Usuario::leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'permUsuarios.cod_persona')
        ->where('permUsuarios.cod_usuario','=',$cod_usuario);

        if($qry->count() >0)
        {
            $user = $qry->select('permUsuarios.cod_usuario', 'permUsuarios.cod_persona', 'permUsuarios.contrasena', 'permUsuarios.obj_permisos','permUsuarios.obj_ou','permUsuarios.ruta_login','permUsuarios.ind_visita_simplificada','permUsuarios.cod_tema_lector', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona')->first();
            //Log::write('acceso', 'retrieveById busca:',['user'=>$user,'cod_usuario'=>$user['cod_usuario']]);
            $expiresAt = Carbon::now()->addMinutes(10);
            Cache::put("user_".$user['cod_usuario'],$user, $expiresAt);
            return $user;
        }

        return null;
    }

    /**
     * Retrieve a user by by their unique identifier and "remember me" token.
     *
     * @param  mixed $cod_usuario
     * @param  string $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($cod_usuario, $token)
    {
        Log::channel('acceso')->info('retrieveByToken:',['user'=>$cod_usuario,"token"=>$token]);

        $qry = Usuario::where('cod_usuario','=',$cod_usuario)
        ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'permUsuarios.cod_persona')
        ->where('remember_token','=',$token);

        if($qry->count() >0)
        {
            $user = $qry->select('permUsuarios.cod_usuario', 'permUsuarios.cod_persona', 'permUsuarios.contrasena', 'permUsuarios.obj_permisos','permUsuarios.obj_ou','permUsuarios.ruta_login','permUsuarios.ind_visita_simplificada','permUsuarios.cod_tema_lector', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona')->first();
            return $user;
        }
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  Authenticatable $user
     * @param  string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
//        Log::write('acceso', 'updateRememberToken:',['user'=>$user,"token"=>$token]);
        Log::channel('acceso')->info('updateRememberToken:',['user'=>$user,"token"=>$token]);

        $user->setRememberToken($token);
//        $user->save();
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
//        Log::write('acceso', 'retrieveByCredentials:',['credentials'=>$credentials]);
//        Log::channel('acceso')->info('retrieveByCredentials:',['credentials'=>$credentials]);
        $qry = Usuario::leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'permUsuarios.cod_persona')
        ->where('permUsuarios.cod_usuario','=',$credentials['cod_usuario']);

        if($qry->count() >0)
        {
            $user = $qry->select('permUsuarios.cod_usuario', 'permUsuarios.cod_persona', 'permUsuarios.contrasena', 'permUsuarios.obj_permisos','permUsuarios.obj_ou','permUsuarios.ruta_login','permUsuarios.ind_visita_simplificada','permUsuarios.cod_tema_lector', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona')->first();
            return $user;
        }
        return null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  Authenticatable $user
     * @param  array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
//        Log::write('acceso', 'validateCredentials:',['user'=>$user,'credentials'=>$credentials]);
//        Log::channel('acceso')->info('validateCredentials:',['user'=>$user,'credentials'=>$credentials]);
        if($user->cod_usuario == $credentials['cod_usuario'] && Hash::check($credentials['contrasena'],$user->contrasena))
        {
            $expiresAt = Carbon::now()->addMinutes(10);
            Cache::put("user_".$credentials['cod_usuario'],$user, $expiresAt);
            //$user->last_login_time = Carbon::now();
            //$user->save();
            return true;
        }
        return false;


    }
}
