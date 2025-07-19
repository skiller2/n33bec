<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
        \App\Http\Controllers\AuthNoMatchCredentials::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException){
            $statusCode = $exception->getStatusCode();
            if ($statusCode == 500){
                $debug = array();
                if(env("APP_DEBUG")){
                    $exception = \Symfony\Component\Debug\Exception\FlattenException::create($exception);
                    $debug = $exception->toArray();
                }
                return response(["error"=>__("Error Interno"),"debug"=>$debug],$statusCode);
            }
            
            $msg =$exception->getMessage();
            return response(['error' => $msg], $statusCode );
        }

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpEvxception){
            return response(['error' => __('Requiere estar Autenticado')], 401);
        }

        if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException)
        {
            return response(['error' => __('Token is invalid')], 401);
        }
        if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException)
        {
            return response(['error' => __('Expiró la credencial')], 401);
        }
        if ($exception instanceof \Tymon\JWTAuth\Exceptions\JWTException)
        {
            return response(['error' => __('Requiere Autenticar')], 401);
        }
        if ($exception instanceof \Illuminate\Database\QueryException)
        {
            $msg = __("Error accediendo a la base de  datos");
            $campos = array();
            if($exception->errorInfo[0]=="22001"){
                $msg_campo = __("Texto muy largo");
                $re = '/column \'(.*)\'/';
                $match = array();
                preg_match($re, $exception->errorInfo[2],$match);
                $campo = $match[1];
                $campos[$campo] = $msg_campo;
                $msg = __("Dato muy largo para la columna :CAMPO",['CAMPO'=>$campo]);
            }
            if($exception->errorInfo[0]=="22003"){
                $msg_campo = __("Número fuera de rango");
                $re = '/column \'(.*)\'/';
                $match = array();
                preg_match($re, $exception->errorInfo[2],$match);
                $campo = $match[1];
                $campos[$campo] = $msg_campo;
                $msg = __("Dato muy largo para la columna :CAMPO",['CAMPO'=>$campo]);
            }           
			if($exception->errorInfo[0]=="22007"){
                $msg_campo = __("Formato de fecha no válido");
                $re = '/column \'(.*)\'/';
                $match = array();
                preg_match($re, $exception->errorInfo[2],$match);
                $campo = $match[1];
                $campos[$campo] = $msg_campo;
                $msg = __("Fecha no válida para la columna :campo",['campo'=>$campo]);
            }
            if($exception->errorInfo[0]=="23000"){
                $cod_error = $exception->errorInfo[1];
                switch($cod_error){
                    case "1451":
                        $msg = __("No se puede eliminar o modificar, existen registros asociados");
                        break;
                    case "1062":
                        $msg = __("Registro existente");
                        break;
                    case "1048":
                        $msg_campo = __("Campo sin datos");
                        $re = '/column \'(.*)\'/i';
                        $match = array();
                        preg_match($re, $exception->errorInfo[2],$match);
                        $campo = $match[1];
                        $campos[$campo] = $msg_campo;
                        $msg = __("El campo :CAMPO debe contener datos",['CAMPO'=>$campo]);
                        break;
                    default:
                        $msg = __("Error clave");
                }
            }

            return response(['error' => $msg, 'campos'=>$campos], Response::HTTP_CONFLICT);
        }

        if ($exception instanceof \App\Http\Controllers\AuthEmtpyPassword)
        {
            return response(['error' => __('Debe ingresar contraseña')], 401);
        }

        if ($exception instanceof \App\Http\Controllers\AuthNoMatchCredentials)
        {
            return response(['error' => __('Usuario y/o contraseña no válidos')], 401);
        }
        if ($exception instanceof \App\Http\Controllers\AuthNoMatchCredentials)
        {
            return response(['error' => __('Usuario y/o contraseña no válidos')], 401);
        }
        if ($exception instanceof \ErrorException)
        {
            $debug = array();
            if(env("APP_DEBUG")){
                $exception = \Symfony\Component\Debug\Exception\FlattenException::create($exception);
                $debug = $exception->toArray();
            }
            return response(["error"=>__("Error Interno"),"debug"=>$debug], 500);
        }

        return response(['error' => __('Error desconocido')], 500);

    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => __('Unauthenticated.')], 401);
        }

        return redirect()->guest('login');
    }
}
