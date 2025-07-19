<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Middleware\BaseMiddleware;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Auth\Access\Gate;
use Symfony\Component\HttpFoundation\Response;
//use App\Http\Controllers\UnidadesOrganizativas;

class PepaAuthorize 
{
    protected $auth;
    protected $gate;
    
    public function __construct(Auth $auth, Gate $gate)
    {
        $this->auth = $auth;
        $this->gate = $gate;
    }
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    
    private function hasAccess($ability) 
    {
        if($ability=="")
            return true;
        $user = $this->auth->user();
        if($user['cod_usuario']=="admin") return true;
        $permisos = is_array($user['obj_permisos'])?$user['obj_permisos']:array();
        return in_array($ability,$permisos);
    }
    
    public function handle($request, Closure $next, $guard = null)
    {
        $controller = explode("@",$request->route()->getActionName());
        $clase = $controller[0];
        $metodo = $controller[1];
        $ability = "";
        try{
            $ability = call_user_func_array(array($clase,'getAbility'),array($metodo));
        }catch(\Exception $e){}
        
        if(!$this->hasAccess($ability)){
            return response(['error' => __("El usuario no tiene permisos (:ABILITY)",['ABILITY'=> $ability])], Response::HTTP_FORBIDDEN);
        }
        return $next($request);
        
        //$accion = class_basename($request->route()->getActionName());
    }   
}
