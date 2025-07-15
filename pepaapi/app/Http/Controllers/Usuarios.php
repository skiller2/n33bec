<?php

namespace App\Http\Controllers;

use JWTAuth;


use App\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use function response;
use Illuminate\Support\Facades\Log;
use App\Traits\Libgeneral;
use Illuminate\Support\Facades\DB;


class AuthNoMatchCredentials extends \Exception
{
};
class AuthEmtpyPassword extends \Exception
{
};
class AuthNoMatchNewPassword extends \Exception
{
};
class Usuarios extends Controller
{
    public static function getAbilities()
    {
        $abs[] = array("den" => "ab_config", "nom" => "Configuración del sistema");
        $abs[] = array("den" => "ab_usuarios", "nom" => "Administración de usuarios");
        $abs[] = array("den" => "ab_gestion", "nom" => "Gestión general");
        $abs[] = array("den" => "ab_gestion_visitas", "nom" => "Gestión visitas");
        $abs[] = array("den" => "ab_movimientos", "nom" => "Consulta movimientos");
        $abs[] = array("den" => "ab_conf_alar", "nom" => "Configuración de sucesos");
        $abs[] = array("den" => "ab_asistencia", "nom" => "Gestión de asistencias");
        $abs[] = array("den" => "ab_resetsucesos", "nom" => "Reset sucesos");
        return $abs;
    }

    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "store":
            case "update":
            case "delete":
            case "gridOptions":
            case "detalle":
                return "ab_usuarios";
            default:
                return "";
        }
    }

    public function index(Request $request, $export)
    {
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'cod_usuario';
        $order = 'asc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTabla($fieldName);
        //        DB::connection()->enableQueryLog();
        $query = Usuario::select(
            'permUsuarios.cod_usuario',
            'permUsuarios.cod_persona',
            'permUsuarios.obj_permisos',
            'permUsuarios.obj_ou',
            'permUsuarios.obj_sectores',
            'permUsuarios.obj_esquemas',
            'maesSectores.nom_sector as sector_default',
            'permUsuarios.esquema_default',
            'permUsuarios.ind_estado',
            'permUsuarios.ind_visita_simplificada',
            'permUsuarios.aud_stm_ingreso',
            'maesPersonas.nom_persona',
            'maesPersonas.ape_persona'
        )
            ->join('maesPersonas', 'maesPersonas.cod_persona', '=', 'permUsuarios.cod_persona')
            ->leftjoin('maesSectores', 'maesSectores.cod_sector', '=', 'permUsuarios.sector_default');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {

                    if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    } else if (strpos($nombre, 'obj') === 0) {
                        $operacion = "JSON";
                    }
                    $tabla = self::getTabla($nombre);

                    switch ($operacion) {
                        case 'LIKE':
                            $valor = "%" . $valor . "%";
                            $query->where($tabla . $nombre, $operacion, $valor);
                            break;
                        case 'IN':
                            $query->whereIn($tabla . $nombre, $valor);
                            break;
                        case 'JSON':
                            foreach ($valor as $val) {
                                $query->where($tabla . $nombre, "LIKE", "%" . $val . "%");
                            }
                            break;
                        case 'MATCH':
                            $query->whereRaw("MATCH(maesPersonas.nom_persona, maesPersonas.ape_persona, maesPersonas.nro_documento)AGAINST('$valor' IN BOOLEAN MODE)");
                            break;
                        default:
                            $query->where($tabla . $nombre, $operacion, $valor);
                            break;
                    }
                }
            }
        }
        $q = $query->orderBy($tablaOrden . $fieldName, $order)->paginate($pageSize);
        $queries = DB::getQueryLog();
        return $q;
    }

    private static function getTabla($campo)
    {
        $tabla = "";
        switch ($campo) {
            case "nom_persona":
            case "ape_persona":
                $tabla = "maesPersonas.";
                break;
            default:
                $tabla = "permUsuarios.";
                break;
        }
        return $tabla;
    }

    public function gridOptions($version = "")
    {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "cod_usuario", "name" => "Cod. Usuario", "key" => "cod_usuario");
                $columnDefs[] = array("prop" => "cod_persona", "name" => "Cod. Persona");
                $columnDefs[] = array("prop" => "ape_persona", "name" => "Apellido");
                $columnDefs[] = array("prop" => "nom_persona", "name" => "Nombre");
                $columnDefs[] = array("prop" => "obj_permisos", "name" => "Permisos", "pipe" => "ftAbilities");
                $columnDefs[] = array("prop" => "obj_ou", "name" => "Organizaciones", "pipe" => "ftOU");
                $columnDefs[] = array("prop" => "obj_sectores", "name" => "Sectores", "pipe" => "ftSectores");
                $columnDefs[] = array("prop" => "sector_default", "name" => "Sector Default Visita");
                $columnDefs[] = array("prop" => "obj_esquemas", "name" => "Esquemas", "pipe" => "ftEsquemas");
                $columnDefs[] = array("prop" => "esquema_default", "name" => "Esquema Default Visita");
                $columnDefs[] = array("prop" => "ind_estado", "name" => "Dado de baja", "pipe" => "ftBoolean");
                $columnDefs[] = array("prop" => "aud_stm_ingreso", "name" => "Fecha Alta", "pipe" => "ftDateTime");
                break;
            default:
                $columnDefs[] = array("field" => "cod_usuario", "displayName" => "Cod. Usuario");
                $columnDefs[] = array("field" => "cod_persona", "displayName" => "Cod. Persona");
                $columnDefs[] = array("field" => "ape_persona", "displayName" => "Apellido");
                $columnDefs[] = array("field" => "nom_persona", "displayName" => "Nombre");
                $columnDefs[] = array("field" => "obj_permisos", "displayName" => "Permisos", "cellFilter" => "ftPermisos");
                $columnDefs[] = array("field" => "obj_ou", "displayName" => "Organizaciones", "cellFilter" => "ftOU");
                $columnDefs[] = array("field" => "obj_sectores", "displayName" => "Sectores");
                $columnDefs[] = array("field" => "sector_default", "displayName" => "Sector Default Visita");
                $columnDefs[] = array("field" => "obj_esquemas", "displayName" => "Esquemas");
                $columnDefs[] = array("field" => "esquema_default", "displayName" => "Esquema Default Visita");
                $columnDefs[] = array("field" => "ind_estado", "displayName" => "Dado de baja", "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "aud_stm_ingreso", "displayName" => "Fecha Alta", "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['cod_usuario'];

        $filtros[] = array('id' => 'cod_usuario', 'name' => 'Cód. Usuario');
        $filtros[] = array('id' => 'cod_persona', 'name' => 'Cód. Persona');
        $filtros[] = array('id' => 'des_persona', 'name' => 'Apellido y Nombre');

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys" => $columnKeys, "columnDefs" => $columnDefs, "filtros" => $filtros, "rango" => $rango);
    }

    public function detalle($clave)
    {
        $clave = json_decode(base64_decode($clave), true);
        if (empty($clave[0]))
            return response(['error' => "Debe seleccionar registro"], Response::HTTP_CONFLICT);
        $cod_usuario = $clave[0][0];
        //$resultado=Usuario::find($cod_usuario);
        $resultado = Usuario::select(
            'permUsuarios.cod_usuario',
            'permUsuarios.cod_persona',
            'permUsuarios.obj_permisos',
            'permUsuarios.ind_estado',
            'permUsuarios.ind_visita_simplificada',
            'permUsuarios.obj_sectores',
            'permUsuarios.obj_esquemas',
            'permUsuarios.sector_default',
            'permUsuarios.esquema_default',
            'permUsuarios.cod_tema_lector',
            'permUsuarios.ruta_login',
            'permUsuarios.obj_ou',
            'maesPersonas.ape_persona',
            'maesPersonas.nom_persona',
            'maesPersonas.nro_documento'
        )
            ->join('maesPersonas', 'permUsuarios.cod_persona', '=', 'maesPersonas.cod_persona')
            ->where('permUsuarios.cod_usuario', '=', $cod_usuario)
            ->get();
        if (count($resultado) > 0) {
            $resultado = $resultado[0];
            $busq_persona = new \stdClass();
            $busq_persona->cod_persona = $resultado->cod_persona;
            $busq_persona->des_persona = $resultado->nom_persona . " " . $resultado->ape_persona . " " . $resultado->nro_documento;
            $busq_persona->nro_documento = $resultado->nro_documento;
            $resultado->busq_persona = $busq_persona;
        }

        return $resultado;
    }

    //Alta de usuario
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_usuario' => 'required',
            'contrasena' => 'required',
            'cod_persona' => 'required'
        ], [
            'cod_usuario.required' => "Debe ingresar Cód. Usuario",
            'contrasena.required' => "Debe ingresar Contraseña",
            'cod_persona.required' => "Debe seleccionar Persona"
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $usuario = new Usuario;
        $usuario->cod_usuario = $request->input('cod_usuario');
        $usuario->contrasena = Hash::make($request->input('contrasena'));
        $usuario->cod_persona = $request->input('cod_persona');
        $usuario->obj_permisos = $request->input('obj_permisos');
        $usuario->obj_ou = $request->input('obj_ou');
        $usuario->obj_sectores = $request->input('obj_sectores');
        $usuario->obj_esquemas = $request->input('obj_esquemas');
        $usuario->sector_default = $request->input('sector_default');
        $usuario->esquema_default = $request->input('esquema_default');
        $usuario->ruta_login = $request->input('ruta_login');
        $usuario->cod_tema_lector = $request->input('cod_tema_lector');
        $usuario->ind_estado = $request->input('ind_estado');
        $usuario->ind_visita_simplificada = $request->input('ind_visita_simplificada');
        Usuario::addAuditoria($usuario, "A");

        $usuario->save();

        return (array('ok' => "Se grabó usuario " . $usuario->cod_usuario));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_usuario' => 'required',
            'cod_persona' => 'required'
        ], [
            'cod_usuario.required' => "Debe ingresar Cód. Usuario",
            'cod_persona.required' => "Debe seleccionar Persona"
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $sector_default = $request->input('sector_default');
        $esquema_default = $request->input('esquema_default');
        $vasectores = (is_array($request->input('obj_sectores'))) ? $request->input('obj_sectores') : array();

        if (empty($vasectores)) $sector_default = "";
        if (empty($request->input('obj_esquemas'))) $esquema_default = "";

        if ($sector_default != "" && !in_array($sector_default, $vasectores))
            return response(['error' => "Sector Default debe estar seleccionado en lista de Sectores"], Response::HTTP_CONFLICT);
        if ($esquema_default != "" && !in_array($esquema_default, $request->input('obj_esquemas')))
            return response(['error' => "Esquema Default debe estar seleccionado en lista de Esquemas"], Response::HTTP_CONFLICT);


        $cod_usuario = $request->input('cod_usuario');
        $usuario = Usuario::find($cod_usuario);
        $usuario->cod_persona = $request->input('cod_persona');
        $usuario->obj_permisos = $request->input('obj_permisos');
        $usuario->obj_ou = $request->input('obj_ou');
        $usuario->obj_sectores = $request->input('obj_sectores');
        $usuario->obj_esquemas = $request->input('obj_esquemas');
        $usuario->sector_default = $request->input('sector_default');
        $usuario->esquema_default = $request->input('esquema_default');
        $usuario->cod_tema_lector = $request->input('cod_tema_lector');
        $usuario->ruta_login = $request->input('ruta_login');
        $usuario->ind_estado = $request->input('ind_estado');
        $usuario->ind_visita_simplificada = $request->input('ind_visita_simplificada');
        if ($request->input('contrasena') != "") {
            $usuario->contrasena = Hash::make($request->input('contrasena'));
        }
        Usuario::addAuditoria($usuario, "M");
        $usuario->save();

        return (array('ok' => "Actualización exitosa " . $cod_usuario));
    }

    public function update_pass(Request $request, $cod_usuario)
    {
        $usuario = Usuario::find($cod_usuario);
        $usuario->contrasena = Hash::make($request->input('contrasena_nueva'));

        Usuario::addAuditoria($usuario, "M");
        $usuario->save();
        return (array('ok' => "Actualización exitosa #" . $cod_usuario));
    }


    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true);
        $cod_usuario = $clave[0][0];

        $usuario = Usuario::find($cod_usuario);
        $usuario->delete();
        return (array('ok' => "Se eliminó satisfactoriamente el usuario #" . $cod_usuario));
    }

    public function signin(Request $request)
    {
        $credentials = $request->only('cod_usuario', 'contrasena');
        $cod_usuario = $credentials['cod_usuario'];
        if ($credentials['contrasena'] == "") {
            throw new AuthEmtpyPassword;
        }
        //		$customClaims = [ 'name' => $user->name, 'username' => $user->username, 'role'=> $user->userRole->role->name, 'timestamp'=>'test', ];

        if (!$token = JWTAuth::attempt($credentials)) {
            throw new AuthNoMatchCredentials;
        }

        if ($request->input('ind_cambio_pass') === true) {
            if ($request->input('contrasena_nueva') != $request->input('confirma_contrasena'))
                throw new AuthNoMatchNewPassword;

            $this->update_pass($request, $cod_usuario);
        }
        $ruta_login = (auth()->user()['ruta_login']) ? auth()->user()['ruta_login'] : "";
        $ind_visita_simplificada = (auth()->user()['ind_visita_simplificada']) ? auth()->user()['ind_visita_simplificada'] : "";
        $cod_tema_lector = (auth()->user()['cod_tema_lector']) ? auth()->user()['cod_tema_lector'] : "";
        Log::channel('acceso')->info('Acceso usuario:', ['user' => $credentials['cod_usuario']]);

        return response(array("token" => $token, "ruta_login" => $ruta_login, "ind_visita_simplificada" => $ind_visita_simplificada, "cod_tema_lector" => $cod_tema_lector))
            ->header('Access-Control-Expose-Headers', 'Authorization')
            ->header('Authorization', 'Bearer ' . $token);
    }

    public function getPersona($buscar)
    {
        if (strlen($buscar) > 2) {
            $buscar = str_replace(' ', '* +', $buscar);
            $datosPersona = DB::select(DB::raw("SELECT CONCAT(maesPersonas.nom_persona,' ',maesPersonas.ape_persona,' ',maesPersonas.nro_documento) as des_persona, 
                                                maesPersonas.cod_persona, maesPersonas.nro_documento, maesPersonas.ape_persona, maesPersonas.nom_persona
                                                FROM maesPersonas 
                                                WHERE MATCH (maesPersonas.nro_documento, maesPersonas.ape_persona, maesPersonas.nom_persona)  AGAINST (? IN BOOLEAN MODE)
                                                LIMIT 40"), array("+$buscar*"));
            return $datosPersona;
        }
    }

    public function getPersonaxOU($buscar, $cod_ou)
    {
        if ($cod_ou == "")
            return response(['error' => "Debe selecciona OU Contacto"], Response::HTTP_CONFLICT);
        if (strlen($buscar) > 2) {
            $buscar = str_replace(' ', '* +', $buscar);
            $datosPersona = DB::select(DB::raw("SELECT DISTINCT CONCAT(c.nom_persona,' ',c.ape_persona,' ',c.nro_documento) as des_persona, 
                                                c.cod_persona, c.nro_documento, c.ape_persona, c.nom_persona, c.obs_visitas 
                                                FROM habiCredPersona b 
                                                JOIN maesPersonas c ON c.cod_persona = b.cod_persona
                                                WHERE b.cod_ou_hab = '$cod_ou' AND b.tipo_habilitacion = 'P' AND c.ind_bloqueo <> 1
                                                AND MATCH (c.nro_documento, c.ape_persona, c.nom_persona) AGAINST (? IN BOOLEAN MODE)
                                                LIMIT 40 "), array("+$buscar*"));
            return $datosPersona;
        }
    }
}
