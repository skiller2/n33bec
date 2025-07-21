<?php

namespace App\Http\Controllers;

use App\AptoFisico;
use App\Credencial;
use App\Esquema;
use App\HabiAcceso;
use App\HabiCredGrupo;
use App\HabiCredPersona;
use App\HabiCredSectores;
use App\Helpers\ConfigParametro;
use App\Imagen;
use App\MoviPersConCred;
use App\Persona;
use App\UnidadesOrganiz;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Traits\Libgeneral;
use stdClass;
use XBase\Table;



use function response;
use function storage_path;

class Habilitaciones extends Controller
{

    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "store":
            case "update":
            case "importa":
            case "delete":
            case "gridOptions":
            case "detalle":
            case "upload":
            case "estadoCred":
                return "ab_gestion";
            default:
                return "";
        }
    }

    public function estadoCred($cod_credencial_bus, $tipo_habilitacion_bus)
    {
        $cod_credencial = "";
        $ref_credencial = "";
        $tipo_habilitacion = "";
        $des_ref_credencial = "";
        $query = Credencial::select('cod_credencial', 'ref_credencial', 'tipo_habilitacion')
            ->where('ref_credencial', '=', $cod_credencial_bus)->orWhere('cod_credencial', '=', $cod_credencial_bus)
            ->get();
        if (!empty($query[0])) {
            $cod_credencial = $query[0]['cod_credencial'];
            $tipo_habilitacion = $query[0]['tipo_habilitacion'];
            $ref_credencial = $query[0]['ref_credencial'];
            if ($tipo_habilitacion != $tipo_habilitacion_bus) {
                return response(['error' => __('Cód. Tarjeta :COD_CREDENCIAL (:REF_CREDENCIAL) existente en stock con tipo de habilitación distinta a la solicitada',['COD_CREDENCIAL'=>$cod_credencial,'REF_CREDENCIAL'=> $ref_credencial])], Response::HTTP_CONFLICT);
            }
        } else
            $cod_credencial = $cod_credencial_bus;

        $query = HabiCredPersona::select(
            'habiCredPersona.cod_persona',
            'habiCredPersona.tipo_credencial',
            'habiCredPersona.tipo_habilitacion',
            'habiCredPersona.cod_persona_contacto',
            'habiCredGrupo.cod_grupo',
            'habiCredPersona.cod_ou_hab',
            'habiCredSectores.cod_sector',
            'habiCredPersona.cod_esquema_acceso',
            'habiCredPersona.stm_habilitacion_desde',
            'habiCredPersona.stm_habilitacion_hasta',
            'habiCredPersona.obs_habilitacion',
            'maesPersonas.ape_persona',
            'maesPersonas.nom_persona',
            'maesPersonas.obs_ult_habilitacion'
        )
            ->leftjoin('habiCredGrupo', 'habiCredGrupo.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('habiCredSectores', 'habiCredSectores.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona_contacto')
            ->where('habiCredPersona.cod_credencial', '=', $cod_credencial)
            ->get();
        if (!empty($query[0])) {
            if ($ref_credencial != "")
                $des_ref_credencial .= ' (' . $ref_credencial . ')';
            return response(['error' => __("Cód. Tarjeta :COD_CREDENCIAL :DES_REF_CREDENCIAL ya asignada",['COD_CREDENCIAL'=>$cod_credencial,'DES_REF_CREDENCIAL'=> $des_ref_credencial])], Response::HTTP_CONFLICT);
        }
        return response(array("ok" => __("Credencial disponible"), "cod_credencial" => $cod_credencial, "ref_credencial" => $ref_credencial), Response::HTTP_ACCEPTED);
    }


    public function valida(Request $request, $campo, $valor, $cod_ou)
    {
        if (!$cod_ou || $cod_ou == "" || $cod_ou == "false")
            return response(['error' => __('Debe seleccionar Unidad Organizacional')], Response::HTTP_CONFLICT);
        $clave = json_decode(base64_decode($valor, true), true);
        if ($campo == "cod_credencial") {
            $es_referencia = false;
            $cod_esquema_acceso = "";
            $cod_persona = "";
            $cod_grupo = "";
            $cod_ou_contacto = "";
            $cod_persona_contacto = "";
            $stm_habilitacion_desde = "";
            $stm_habilitacion_hasta = "";
            $tipo_credencial = "";
            $tipo_habilitacion = "";
            $obs_habilitacion = "";
            $obs_ult_habilitacion = "";
            $ind_movimiento = "";
            $stm_habilitacion = "";
            $des_persona_contacto = "";
            $ref_credencial = "";
            $vaSectores = array();
            $cod_credencial_referencia = $clave[0];

            $ind_hab = $clave[1]; //T : Visitas, P: Habilitaciones permanentes

            if (is_numeric($cod_credencial_referencia))
                $cod_credencial_referencia = (int) $cod_credencial_referencia;
            $cod_credencial_referencia = (string) $cod_credencial_referencia;

            $query = Credencial::select('cod_credencial', 'tipo_habilitacion')
                ->where('ref_credencial', '=', $cod_credencial_referencia)
                ->get();

            if (!empty($query[0])) {
                $cod_credencial = $query[0]['cod_credencial'];
                $tipo_habilitacion = $query[0]['cod_credencial'];
                $es_referencia = true;
            } else {
                $cod_credencial = $clave[0];
            }

            $stockcred = false;
            $stock = Credencial::select('ref_credencial', 'tipo_habilitacion')->where('cod_credencial', '=', $cod_credencial)->get();
            if (!empty($stock[0])) {
                $ref_credencial = $stock[0]['ref_credencial'];

                if ($ind_hab == 'P' && $stock[0]['tipo_habilitacion'] == 'T') {
                    return response(['error' => __('Cód. Tarjeta :COD_CREDENCIAL :REF_CREDENCIAL existente en stock con habilitación temporal',['COD_CREDENCIAL'=>$cod_credencial , 'REF_CREDENCIAL'=>$ref_credencial])], Response::HTTP_CONFLICT);
                }
                if ($ind_hab == 'T' && $stock[0]['tipo_habilitacion'] == 'P') {
                    return response(['error' => __('Cód. Tarjeta temporal :COD_CREDENCIAL :REF_CREDENCIAL inexistente en stock',['COD_CREDENCIAL'=>$cod_credencial , 'REF_CREDENCIAL'=>$ref_credencial])], Response::HTTP_CONFLICT);
                }
                $stockcred = true;
            } else if ($ind_hab == "T") {
                return response(['error' => __('Cód. Tarjeta :COD_CREDENCIAL :REF_CREDENCIAL inexistente en stock para habilitación temporal',['COD_CREDENCIAL'=>$cod_credencial , 'REF_CREDENCIAL'=>$ref_credencial])], Response::HTTP_CONFLICT);
            }

            $query = HabiCredPersona::select(
                'habiCredPersona.cod_persona',
                'habiCredPersona.tipo_credencial',
                'habiCredPersona.tipo_habilitacion',
                'habiCredPersona.cod_persona_contacto',
                'habiCredGrupo.cod_grupo',
                'habiCredPersona.cod_ou_hab',
                'habiCredSectores.cod_sector',
                'habiCredPersona.cod_esquema_acceso',
                'habiCredPersona.stm_habilitacion_desde',
                'habiCredPersona.stm_habilitacion_hasta',
                'habiCredPersona.obs_habilitacion',
                'maesPersonas.ape_persona',
                'maesPersonas.nom_persona',
                'maesPersonas.obs_ult_habilitacion'
            )
                ->leftjoin('habiCredGrupo', 'habiCredGrupo.cod_credencial', '=', 'habiCredPersona.cod_credencial')
                ->leftjoin('habiCredSectores', 'habiCredSectores.cod_credencial', '=', 'habiCredPersona.cod_credencial')
                ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona_contacto')
                ->where('habiCredPersona.cod_credencial', '=', $cod_credencial)
                ->get();
            if (!empty($query[0])) {
                $des_error = $cod_credencial;
                if ($ref_credencial != "")
                    $des_error .= ' (' . $ref_credencial . ')';
                return response(['error' => __('Cód. Tarjeta :DES_ERROR ya asignada',['DES_ERROR'=>$des_error])], Response::HTTP_CONFLICT);


                $row = $query[0];
                $cod_persona = $row['cod_persona'];
                $tipo_credencial = $row['tipo_credencial'];
                $tipo_habilitacion = $row['tipo_habilitacion'];
                $obs_habilitacion = $row['obs_habilitacion'];
                $obs_ult_habilitacion = $row['obs_ult_habilitacion'];

                $cod_esquema_acceso = $row['cod_esquema_acceso'];
                $cod_grupo = $row['cod_grupo'];
                $cod_ou_contacto = $row['cod_ou_hab'];
                $cod_persona_contacto = $row['cod_persona_contacto'];
                $des_persona_contacto = $row['ape_persona'] . " " . $row['nom_persona'];
                $stm_habilitacion_desde = $row['stm_habilitacion_desde'];
                $stm_habilitacion_hasta = $row['stm_habilitacion_hasta'];
                foreach ($query as $row) {
                    $vaSectores[] = $row['cod_sector'];
                }
            }

            return array(
                "cod_credencial" => $cod_credencial, "cod_persona_contacto" => $cod_persona_contacto, "des_persona_contacto" => $des_persona_contacto,
                "ref_credencial" => $ref_credencial, "tipo_credencial" => $tipo_credencial, "tipo_habilitacion" => $tipo_habilitacion, "ind_movimiento" => $ind_movimiento,
                "stm_habilitacion" => $stm_habilitacion, "cod_esquema_acceso" => $cod_esquema_acceso, "stm_habilitacion_desde" => $stm_habilitacion_desde,
                "stm_habilitacion_hasta" => $stm_habilitacion_hasta, "sectoresSel" => $vaSectores, "cod_persona" => $cod_persona, "cod_grupo" => $cod_grupo,
                "cod_ou_contacto" => $cod_ou_contacto, "stockcred" => $stockcred, "obs_habilitacion" => $obs_habilitacion, "obs_ult_habilitacion" => $obs_ult_habilitacion
            );
        } else if ($campo == "cod_persona") {
            $datosPersona = array();
            $datosCred = array();
            $vacredenciales = array();
            $vccredenciales = "";
            $cod_persona = $clave[0];

            $query = Persona::select('maesPersonas.ape_persona', 'maesPersonas.nom_persona', 'maesPersonas.nro_documento', 'maesPersonas.cod_tipo_doc', 'maesPersonas.cod_sexo', 'maesPersonas.email', 'maesPersonas.obs_visitas', 'maesPersonas.obs_visitas', 'maesPersonas.obs_ult_habilitacion')
                ->where('maesPersonas.cod_persona', '=', $cod_persona)
                ->get();
            if (empty($query[0])) {
                return response(array("error" => __("Persona no localizada :COD_PERSONA",['COD_PERSONA'=>$cod_persona])), Response::HTTP_NOT_FOUND);
            }
            $row = $query[0];
            $datosPersona = array(
                "ape_persona" => $row['ape_persona'], "nom_persona" => $row['nom_persona'],
                "cod_tipo_doc" => $row['cod_tipo_doc'], "nro_documento" => $row['nro_documento'],
                "des_persona" => $row['ape_persona'] . " " . $row['nom_persona'],
                "documento" => $row['cod_tipo_doc'] . " " . $row['nro_documento'],
                "cod_sexo" => $row['cod_sexo'], "email" => $row['email'],
                "obs_visitas" => $row['obs_visitas'], "obs_habilitacion" => $row['obs_ult_habilitacion'],
                "obs_ult_habilitacion" => $row['obs_ult_habilitacion']
            );

            $moviPersConCred = MoviPersConCred::select('cod_credencial')->where('cod_persona', $cod_persona)->get();
            foreach ($moviPersConCred as $cred) {
                $vacredenciales[] = $cred['cod_credencial'];
            }
            if (!empty($vacredenciales)) {
                $vccredenciales = __("La persona posee credenciales para devolver :credenciales",['credenciales'=>implode(", ", $vacredenciales)]);
            }


            $datosCred = HabiCredPersona::select(
                'habiCredPersona.cod_credencial',
                'habiCredPersona.tipo_habilitacion',
                'habiCredPersona.obs_habilitacion',
                'maesUnidadesOrganiz.nom_ou',
                DB::raw('GROUP_CONCAT(maesSectores.nom_sector) as nom_sector')
            )
                ->where('habiCredPersona.cod_persona', '=', $cod_persona)
                ->join('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
                ->join('habiCredSectores', 'habiCredSectores.cod_credencial', '=', 'habiCredPersona.cod_credencial')
                ->join('maesSectores', 'maesSectores.cod_sector', '=', 'habiCredSectores.cod_sector')
                ->groupBy('habiCredPersona.cod_credencial', 'habiCredPersona.tipo_habilitacion', 'maesUnidadesOrganiz.nom_ou', 'habiCredPersona.obs_habilitacion')
                ->get();

            return array("datosCred" => $datosCred, "datosPersona" => $datosPersona, "vccredenciales" => $vccredenciales);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request, $export)
    {
        /*
          try {
          JWTAuth::parseToken()->toUser();
          } catch (Exception $e) {
          return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_UNAUTHORIZED);
          }
         */
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $cod_ou = json_decode($request->input('cod_ou'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'stm_habilitacion_desde';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        $tablaOrden = self::getTabla($fieldName);
        //DB::enableQueryLog();
        $tabla = "";
        $query = HabiCredPersona::select('habiCredPersona.cod_credencial', 'habiCredPersona.cod_persona', 'habiCredPersona.tipo_credencial', 'habiCredPersona.tipo_habilitacion', 'habiCredPersona.cod_ou_emisora', 'habiCredPersona.cod_persona_contacto', 'habiCredPersona.cod_ou_hab', 'habiCredPersona.tipo_habilitacion', 'habiCredGrupo.cod_grupo', 'confGrupoCred.des_grupo', 'maesUnidadesOrganiz.nom_ou', 'maesPersonas.nro_documento', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona', 'vwCredSectores.nom_sector','habiCredPersona.cod_esquema_acceso', 'habiCredPersona.stm_habilitacion_desde', 'habiCredPersona.stm_habilitacion_hasta')
            ->leftjoin('habiCredGrupo', 'habiCredGrupo.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('confGrupoCred', 'confGrupoCred.cod_grupo', '=', 'habiCredGrupo.cod_grupo')
            ->leftjoin('vwCredSectores', 'vwCredSectores.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            //->leftjoin('habiCredSectores', 'habiCredSectores.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            //->leftjoin('maesSectores', 'maesSectores.cod_sector', '=', 'habiCredSectores.cod_sector')
            ->leftjoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
            ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
            ->leftjoin('maesAliasCred', 'maesAliasCred.cod_credencial', '=', 'habiCredPersona.cod_credencial');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($nombre == "aud_stm_ingreso")
                        $nombre = "stm_habilitacion_desde";
                    else if ($nombre == "ref_credencial") {
                        $operacion = "=";
                        if (is_numeric($valor)) {
                            $valor = (int) $valor;
                            $valor = (string) $valor;
                        }
                    } else if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }

                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $tabla = self::getTabla($nombre);
                    if ($operacion == "MATCH") {
                        $query->whereRaw("MATCH(maesPersonas.nom_persona, maesPersonas.ape_persona, maesPersonas.nro_documento)AGAINST('$valor' IN BOOLEAN MODE)");
                    } else {
                        $query->where($tabla . $nombre, $operacion, $valor);
                    }
                }
            }
        }
        $query->where("habiCredPersona.cod_ou_emisora", $cod_ou);
//            ->groupBy('habiCredPersona.cod_credencial', 'habiCredPersona.cod_ou_emisora', 'habiCredPersona.cod_persona_contacto', 'habiCredPersona.cod_ou_hab', 'habiCredPersona.cod_persona', 'habiCredPersona.tipo_habilitacion', 'habiCredPersona.tipo_credencial', 'habiCredGrupo.cod_grupo', 'confGrupoCred.des_grupo', 'maesPersonas.nro_documento', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona', 'habiCredPersona.cod_esquema_acceso', 'habiCredPersona.stm_habilitacion_desde', 'habiCredPersona.stm_habilitacion_hasta', 'maesUnidadesOrganiz.nom_ou', 'maesAliasCred.ref_credencial');

        $query->orderBy($tablaOrden . $fieldName, $order);

        if ($export == "false") {
            $vvrespuesta = $query->paginate($pageSize);
            foreach ($vvrespuesta as $index => $valor) {
                $vvrespuesta[$index]['des_persona'] = $valor['ape_persona'] . " " . $valor['nom_persona'];
            }
            return $vvrespuesta;
        } else {

            switch ($export) {
                case "xls":
                    $typeExp = Type::XLSX;
                    break;
                case "csv":
                    $typeExp = Type::CSV;
                    break;
                case "ods":
                    $typeExp = Type::ODS;
                    break;
                default:
                    $typeExp = Type::XLSX;
                    break;
            }
            $fileName = __("Habilitaciones_Permanentes.:TYPEEXP",['TYPEEXP'=>$typeExp]);
            $writer = WriterFactory::create($typeExp); // for XLSX files
            $writer->openToBrowser($fileName); // stream data directly to the browser
            $timezoneGMT = new DateTimeZone('GMT');
            $timezoneApp = new DateTimeZone(ConfigParametro::get('TIMEZONE_INFORME', false));

            $query->chunk(1000, function ($multipleRows) use ($writer, $timezoneGMT, $timezoneApp) {
                static $FL = true;
                if ($FL) {
                    $writer->addRow(array_keys($multipleRows[0]->getAttributes()));
                    $FL = false;
                }

                $arExport = $multipleRows->toArray();

                foreach ($arExport as &$row) {
                    $fecha = date_create($row['stm_habilitacion_desde'], $timezoneGMT)->setTimeZone($timezoneApp);
                    $row['stm_habilitacion_desde'] = date_format($fecha, "d/m/Y H:i:s");
                    if ($row['stm_habilitacion_hasta']) {
                        $fecha = date_create($row['stm_habilitacion_hasta'], $timezoneGMT)->setTimeZone($timezoneApp);
                        $row['stm_habilitacion_hasta'] = date_format($fecha, "d/m/Y H:i:s");
                    }
                }
                $writer->addRows($arExport);
                unset($arExport);
            });
            $writer->close();
            return;
        }
    }

    private static function getTabla($campo)
    {
        $tabla = "";
        switch ($campo) {
            case "nro_documento":
            case "nom_persona":
            case "ape_persona":
                $tabla = "maesPersonas.";
                break;
            case "cod_grupo":
                $tabla = "habiCredGrpo.";
                break;
            case "cod_sector":
                $tabla = "habiCredSectores.";
                break;
            case "nom_ou":
                $tabla = "maesUnidadesOrganiz.";
                break;
            case "nom_sector":
                $tabla = "maesSectores.";
                break;
            case "des_grupo":
                $tabla = "confGrupoCred.";
                break;
            case "ref_credencial":
                $tabla = "maesAliasCred.";
                break;
            default:
                $tabla = "habiCredPersona.";
                break;
        }
        return $tabla;
    }

    public function gridOptions($version = "")
    {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "cod_credencial", "name" => __("Cód. Tarjeta"), "key" => "cod_credencial");
                $columnDefs[] = array("prop" => "ref_credencial", "name" => __("Ref. Tarjeta"));
                $columnDefs[] = array("prop" => "nom_ou", "name" => __("Organización"));
                $columnDefs[] = array("prop" => "cod_ou", "name" => __("OU"), "visible" => false);
                $columnDefs[] = array("prop" => "nom_sector", "name" => __("Sectores"));
                $columnDefs[] = array("prop" => "nro_documento", "name" => __("DNI"));
                $columnDefs[] = array("prop" => "ape_persona", "name" => __("Apellido"));
                $columnDefs[] = array("prop" => "nom_persona", "name" => __("Nombre"));
                $columnDefs[] = array("prop" => "stm_habilitacion_desde", "name" => __("Fecha Desde"));
                $columnDefs[] = array("prop" => "stm_habilitacion_hasta", "name" => __("Fecha Hasta"));
                break;
            default:
                $columnDefs[] = array("field" => "cod_credencial", "displayName"=> __("Cód. Tarjeta"), "cellFilter" => "ftTarjeta");
                $columnDefs[] = array("field" => "ref_credencial", "displayName"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("field" => "nom_ou", "displayName"=> __("Organización"));
                $columnDefs[] = array("field" => "cod_ou", "displayName"=> __("Codigo Organización"), "visible" => false);
                $columnDefs[] = array("field" => "nom_sector", "displayName"=> __("Sectores"));
                $columnDefs[] = array("field" => "nro_documento", "displayName"=> __("DNI"));
                $columnDefs[] = array("field" => "ape_persona", "displayName"=> __("Apellido"));
                $columnDefs[] = array("field" => "nom_persona", "displayName"=> __("Nombre"));
                $columnDefs[] = array("field" => "stm_habilitacion_desde", "displayName"=> __("Fecha Desde"), "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "stm_habilitacion_hasta", "displayName"=> __("Fecha Hasta"), "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['cod_credencial', 'cod_ou'];

        $filtros[] = array('id' => 'cod_credencial', 'name'=> __("Cód. Tarjeta"));
        $filtros[] = array('id' => 'ref_credencial', 'name'=> __("Ref. Tarjeta"));
        $filtros[] = array('id' => 'nom_ou', 'name'=> __("Organización"));
        $filtros[] = array('id' => 'nom_sector', 'name'=> __("Sectores"));
        $filtros[] = array('id' => 'nro_documento', 'name'=> __("Nro. Documento"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));

        $rango['desde'] = array('id' => 'stm_habilitacion_desde', 'tipo' => 'datetime');
        $rango['hasta'] = array('id' => 'stm_habilitacion_hasta', 'tipo' => 'datetime');

        return array("columnKeys" => $columnKeys, "columnDefs" => $columnDefs, "filtros" => $filtros, "rango" => $rango);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function detalle($clave, $cod_ou)
    {
        $clave = json_decode(base64_decode($clave), true);
        $cod_credencial = $clave[0][0];

        if ($cod_ou == "false")
            return response(['error' => __('Debe seleccionar Unidad Organizacional')], Response::HTTP_CONFLICT);

        $vccod_res = 1; //OK

        $cod_esquema_acceso = "";
        $cod_persona = "";
        $cod_grupo = "";
        $cod_ou_contacto = "";
        $cod_persona_contacto = "";
        $stm_habilitacion_desde = "";
        $stm_habilitacion_hasta = "";
        $tipo_credencial = "";
        $tipo_habilitacion = "";
        $obs_habilitacion = "";
        $ind_movimiento = "";
        $stm_habilitacion = "";
        $des_persona_contacto = "";
        $vaSectores = array();

        $query = HabiCredPersona::select(
            'habiCredPersona.cod_credencial',
            'habiCredPersona.cod_persona',
            'habiCredPersona.tipo_credencial',
            'habiCredPersona.obs_habilitacion',
            'habiCredPersona.tipo_habilitacion',
            'habiCredPersona.cod_ou_emisora',
            'habiCredPersona.cod_persona_contacto',
            'habiCredPersona.cod_ou_hab',
            'habiCredPersona.tipo_habilitacion',
            'habiCredGrupo.cod_grupo',
            'confGrupoCred.des_grupo',
            'maesUnidadesOrganiz.nom_ou',
            'maesPersonas.nro_documento',
            'maesPersonas.nom_persona',
            'maesPersonas.ape_persona',
            'habiCredSectores.cod_sector',
            'habiCredPersona.cod_esquema_acceso',
            'habiCredPersona.stm_habilitacion_desde',
            'habiCredPersona.stm_habilitacion_hasta'
        )
            ->leftjoin('habiCredGrupo', 'habiCredGrupo.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('confGrupoCred', 'confGrupoCred.cod_grupo', '=', 'habiCredGrupo.cod_grupo')
            ->leftjoin('habiCredSectores', 'habiCredSectores.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('maesSectores', 'maesSectores.cod_sector', '=', 'habiCredSectores.cod_sector')
            ->leftjoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
            ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
            ->leftjoin('maesAliasCred', 'maesAliasCred.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->where('habiCredPersona.cod_credencial', '=', $cod_credencial)
            ->get();

        if (!empty($query[0])) {
            $row = $query[0];
            $cod_esquema_acceso = $row['cod_esquema_acceso'];
            $cod_grupo = $row['cod_grupo'];
            $cod_ou_contacto = $row['cod_ou_hab'];
            $cod_persona = $row['cod_persona'];
            $cod_persona_contacto = $row['cod_persona_contacto'];
            $des_persona_contacto = $row['ape_persona'] . " " . $row['nom_persona'];
            $stm_habilitacion_desde = $row['stm_habilitacion_desde'];
            $stm_habilitacion_hasta = $row['stm_habilitacion_hasta'];
            $tipo_credencial = $row['tipo_credencial'];
            $tipo_habilitacion = $row['tipo_habilitacion'];
            $obs_habilitacion = $row['obs_habilitacion'];
            foreach ($query as $row) {
                if ($row['cod_sector'])
                    $vaSectores[] = $row['cod_sector'];
            }
        }
        return array(
            "cod_credencial" => $cod_credencial, "cod_credencial_nueva" => $cod_credencial, "cod_persona_contacto" => $cod_persona_contacto, "des_persona_contacto" => $des_persona_contacto,
            "tipo_credencial" => $tipo_credencial, "tipo_habilitacion" => $tipo_habilitacion, "obs_habilitacion" => $obs_habilitacion,
            "cod_esquema_acceso" => $cod_esquema_acceso, "stm_habilitacion_desde" => $stm_habilitacion_desde, "stm_habilitacion_hasta" => $stm_habilitacion_hasta,
            "sectoresSel" => $vaSectores, "cod_persona" => $cod_persona, "cod_grupo" => $cod_grupo, "cod_ou_contacto" => $cod_ou_contacto
        );
    }


    public function sendHabtoMain(Request $request)
    {

        $voDatosHab = new \stdClass();
        $voDatosHab->cod_credencial = $request->input('cod_credencial');
        $voDatosHab->cod_tipo_doc = $request->input('cod_tipo_doc');
        $voDatosHab->nro_documento = $request->input('nro_documento');
        $voDatosHab->ape_persona = $request->input('ape_persona');
        $voDatosHab->nom_persona = $request->input('nom_persona');
        $voDatosHab->cod_sexo = $request->input('cod_sexo');
        $voDatosHab->email = $request->input('email');

        if ($voDatosHab->ape_persona == "")
            return response(['error' => __('Debe cargar apellido')], Response::HTTP_CONFLICT);
        if ($voDatosHab->nom_persona == "")
            return response(['error' => __('Debe cargar nombre')], Response::HTTP_CONFLICT);
        if ($voDatosHab->nro_documento == "")
            return response(['error' => __('Debe cargar número de documento')], Response::HTTP_CONFLICT);



        $url_envio = "http://10.25.1.1/api/v1/habilitacionesxou";
        $url_envio = "http://10.8.0.8/api/v1/habilitacionesxou";
        $json = json_encode($voDatosHab);
        $ch = curl_init($url_envio);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $jsonres = json_decode($res, true);
        if ($http_status == "0")
            $http_status = Response::HTTP_GONE;
        return response([$jsonres], $http_status);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {

        $temas = ConfigParametro::getTemas("LECTOR");
        $vaTemas = array();

        $cod_credencial = preg_replace('/\D/', '', $request->input('cod_credencial'));
        $cod_credencial_nueva = preg_replace('/\D/', '', $request->input('cod_credencial_nueva'));
        $cod_persona = $request->input('cod_persona');
        $cod_ou = $request->input('cod_ou');
        $sectoresSel = $request->input('sectoresSel');
        $tipo_credencial = $request->input('tipo_credencial');
        $tipo_habilitacion = $request->input('tipo_habilitacion');
        $obs_habilitacion = $request->input('obs_habilitacion');
        $cod_esquema_acceso = $request->input('cod_esquema_acceso');
        $cod_ou_contacto = $request->input('cod_ou_contacto');
        $cod_grupo = $request->input('cod_grupo');
        $cod_persona_contacto = $request->input('cod_persona_contacto');
        $nom_persona = $request->input('nom_persona');
        $ape_persona = $request->input('ape_persona');
        $cod_sexo = $request->input('cod_sexo');
        $cod_tipo_doc = $request->input('cod_tipo_doc');
        $nro_documento = $request->input('nro_documento');
        $email = $request->input('email');
        $obs_visitas = $request->input('obs_visitas');
        $ref_credencial = "";
        $nom_persona_contacto = "";
        $ape_persona_contacto = "";
        $stm_habilitacion_hasta = null;

        $validator = Validator::make($request->all(), [
            //        'cod_credencial' => 'required',
            'cod_ou' => 'required',
            'tipo_habilitacion' => 'required',
            'cod_esquema_acceso' => 'required',
            'sectoresSel' => 'required'
        ], [
            'cod_credencial.required' => __("Debe ingresar Tarjeta"),
            'cod_ou.required' => __("Debe seleccionar Organización"),
            'tipo_habilitacion.required' => __("Debe seleccionar Tipo Habilitación"),
            'cod_esquema_acceso.required' => __("Debe seleccionar Esquema de Acceso"),
            'sectoresSel.required' => __("Debe seleccionar Sectores")
        ]);



        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }
        if (!$cod_ou_contacto)
            return response(['error' => __('Debe ingresar una Organización de contacto')], Response::HTTP_CONFLICT);

        $img_apto_fisico = $request->input('img_apto_fisico');
        $fec_otorgamiento_af = $request->input('fec_otorgamiento_af');

        if ($img_apto_fisico && !$fec_otorgamiento_af) {
            return response(['error' => __('Debe ingresar Fecha Otorgamiento Apto Físico')], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        if ($cod_credencial_nueva != "" && $cod_credencial_nueva !== $cod_credencial) {
            $vvrespuesta = $this->frecambioTarjeta($cod_credencial_nueva, $cod_credencial);
            if ($vvrespuesta->status() == '200')
                $cod_credencial = $cod_credencial_nueva;
            else return $vvrespuesta;
        }

        $stockcred = Credencial::select('tipo_habilitacion', 'ref_credencial')->where('cod_credencial', $cod_credencial)->first();
        if ($stockcred) {
            $ref_credencial = $stockcred['ref_credencial'];
            if ($stockcred['tipo_habilitacion'] != $tipo_habilitacion)
                return response(['error' => __("Tarjeta en stock con tipo de habilitación distinta a la seleccionada")], Response::HTTP_CONFLICT);
        }

        $esq = Esquema::select()->where('cod_esquema_acceso', $cod_esquema_acceso)->first();
        if ($esq) {
            $fec_habilitacion_hasta = $esq['fec_habilitacion_hasta'];
            $stm_actual = Carbon::now()->format('Y-m-d H:i:s');
            if ($fec_habilitacion_hasta < $stm_actual && (int) $fec_habilitacion_hasta != 0) {
                return response(['error' => __("Esquema habilitado hasta :FEC_HABILITACION_HASTA",['FEC_HABILITACION_HASTA'=>$fec_habilitacion_hasta])], Response::HTTP_CONFLICT);
            }
        }

        if ($tipo_habilitacion == "T") {

            $stockcred = Credencial::select('tipo_habilitacion')->where('cod_credencial', $cod_credencial)->first();
            if (!$stockcred) {
                return response(['error'=> __("Tarjeta inexistente en Stock Tarjetas")], Response::HTTP_CONFLICT);
            } else if ($stockcred['tipo_habilitacion'] != "T") {
                return response(['error' => __("La tarjeta ingresada debe ser de tipo Temporal/Visita")], Response::HTTP_CONFLICT);
            }

            $tipo_credencial = "RFID";
            $cod_persona_contacto = $request->input('cod_persona_contacto');
            if (!$cod_persona_contacto)
                return response(['error' => __('Debe ingresar Persona Contacto')], Response::HTTP_CONFLICT);

            $valida_nrodoc_visitas_unico = ConfigParametro::get('VALIDA_NRODOC_VISITAS_UNICO', false);
            if ($valida_nrodoc_visitas_unico) {
                $existe = habiCredPersona::select('habiCredPersona.tipo_habilitacion')
                    ->join('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
                    ->where('habiCredPersona.tipo_habilitacion', $tipo_habilitacion)
                    ->where('maesPersonas.nro_documento', $request->input('nro_documento'))->first();

                if ($existe) {
                    return response(['error' => __('Ya existe una habilitación temporal con el número de DNI indicado')], Response::HTTP_CONFLICT);
                }
            }
        } else {
            $cod_persona_contacto = "";
        }

        if (!$cod_persona || $cod_persona == "") {
            $persona = Persona::select()->where('nro_documento', $request->input('nro_documento'))->first();
            if ($persona) {
                $auditoria = "M";
                $cod_persona = $persona->cod_persona;
            } else {
                $persona = new Persona;
                $cod_persona = Persona::getUuid();
                $persona->cod_persona = $cod_persona;
                $persona->ind_bloqueo = 0;
                $auditoria = "A";
            }
        } else {
            $persona = Persona::find($cod_persona);
            $persona->nro_documento_ant = $persona->nro_documento; //Grabo el nro documento anterior por si se modificó
            $auditoria = "M";
        }

        if ($persona->ind_bloqueo == "1") {
            return response(['error' => __('DNI bloqueado. Motivo :DES_MOTIVO_BLOQUEO',['DES_MOTIVO_BLOQUEO'=>$persona->des_motivo_bloqueo]) ], Response::HTTP_CONFLICT);
        }

        $persona->nom_persona = $nom_persona;
        $persona->ape_persona = $ape_persona;
        $persona->cod_sexo = $cod_sexo;
        $persona->cod_tipo_doc = $cod_tipo_doc;
        $persona->nro_documento = $nro_documento;
        $persona->email = $email;
        $persona->obs_visitas = $obs_visitas;
        $persona->obs_ult_habilitacion = $obs_habilitacion;

        Persona::addAuditoria($persona, $auditoria);


        $persona->save();

        if ($request->input('img_persona') || $request->input('img_documento')) {
            $auditoria = "M";
            $imagenes = Imagen::select()->where('cod_persona', $cod_persona)->first();
            if (!$imagenes) {
                $imagenes = new Imagen;
                $imagenes->cod_persona = $cod_persona;
                $auditoria = "A";
            }
            $imagenes->img_persona = $request->input('img_persona');
            $imagenes->img_documento = $request->input('img_documento');
            Imagen::addAuditoria($imagenes, $auditoria);
            $imagenes->save();
        }

        if ($img_apto_fisico) {

            $plazo_vigencia_apto_fisico = ConfigParametro::get('PLAZO_VIGENCIA_APTO_FISICO', false);
            if (!$plazo_vigencia_apto_fisico) {
                $plazo_vigencia_apto_fisico = "1Y";
            }
            $fec_vencimiento_af = Persona::addDateDiff($plazo_vigencia_apto_fisico, $fec_otorgamiento_af);

            $audit = "M";
            $apto = AptoFisico::find($cod_persona);
            if (!$apto) {
                $apto = new AptoFisico;
                $apto->cod_persona = $cod_persona;
                $audit = "A";
            }

            $apto->img_apto_fisico = $img_apto_fisico;
            $apto->fec_otorgamiento_af = $fec_otorgamiento_af;
            $apto->fec_vencimiento_af = $fec_vencimiento_af;
            $apto->stm_notificacion = null;
            AptoFisico::addAuditoria($apto, $audit);
            $apto->save();
        }

        $auditoria = "M";
        $habilitacion_persona = HabiCredPersona::find($cod_credencial);
        if (!$habilitacion_persona) {
            $habilitacion_persona = new HabiCredPersona;
            $auditoria = "A";
        }

        $habilitacion_persona->cod_credencial = $cod_credencial;
        $habilitacion_persona->cod_persona = $cod_persona;
        $habilitacion_persona->tipo_credencial = $tipo_credencial;
        $habilitacion_persona->tipo_habilitacion = $tipo_habilitacion;
        $habilitacion_persona->obs_habilitacion = $obs_habilitacion;
        $habilitacion_persona->cod_esquema_acceso = $cod_esquema_acceso;
        $habilitacion_persona->cod_ou_emisora = $cod_ou;
        $habilitacion_persona->cod_ou_hab = $cod_ou_contacto;
        HabiCredPersona::addAuditoria($habilitacion_persona, $auditoria);
        $habilitacion_persona->stm_habilitacion_desde = $habilitacion_persona->aud_stm_ingreso;
        $habilitacion_persona->stm_habilitacion_hasta = $stm_habilitacion_hasta;
        $habilitacion_persona->save();

        HabiCredSectores::where('cod_credencial', $cod_credencial_nueva)->delete();
        HabiCredSectores::where('cod_credencial', $cod_credencial)->delete();
        foreach ($sectoresSel as $cod_sector) {
            $habilitacion_sectores = new HabiCredSectores;
            $habilitacion_sectores->cod_credencial = $cod_credencial;
            $habilitacion_sectores->cod_ou = $cod_ou;
            $habilitacion_sectores->cod_sector = $cod_sector;
            HabiCredSectores::addAuditoria($habilitacion_sectores, "A");
            $habilitacion_sectores->save();

            foreach ($temas as $cod_tema => $datos_tema) {
                if ($datos_tema['cod_sector'] == $cod_sector) {
                    $vaTemas[$cod_tema] = $cod_tema;
                }
            }
        }

        if ($cod_grupo) {
            HabiCredGrupo::where('cod_credencial', $cod_credencial)->delete();
            $habilitacion_grupo = new HabiCredGrupo;
            $habilitacion_grupo->cod_credencial = $cod_credencial;
            $habilitacion_grupo->cod_grupo = $cod_grupo;
            HabiCredGrupo::addAuditoria($habilitacion_grupo, "RL");
            $habilitacion_grupo->save();
        } else {
            $cod_grupo = 0;
        }

        //GRABA EN HABIACCESO
        $json_temas = $vaTemas;

        $ou = UnidadesOrganiz::select()->where('cod_ou', $cod_ou)->first();
        $nom_ou = $ou['nom_ou'];

        $ou = UnidadesOrganiz::select()->where('cod_ou', $cod_ou_contacto)->first();
        $nom_ou_contacto = $ou['nom_ou'];
        if ($cod_persona_contacto != "") {
            $persona_contacto = Persona::select()->where('cod_persona', $cod_persona_contacto)->first();
            $nom_persona_contacto = $persona_contacto['nom_persona'];
            $ape_persona_contacto = $persona_contacto['ape_persona'];
        }

        $auditoria = "RL";
        $acceso = HabiAcceso::find($cod_credencial);
        if (!$acceso) {
            $acceso = new HabiAcceso;
        }
        $acceso->cod_credencial = $cod_credencial;
        $acceso->ref_credencial = $ref_credencial;
        $acceso->cod_persona = $cod_persona;
        $acceso->nom_persona = $nom_persona;
        $acceso->ape_persona = $ape_persona;
        $acceso->cod_sexo = $cod_sexo;
        $acceso->cod_tipo_doc = $cod_tipo_doc;
        $acceso->nro_documento = $nro_documento;
        $acceso->tipo_habilitacion = $tipo_habilitacion;
        $acceso->obs_habilitacion = $obs_habilitacion;
        $acceso->cod_grupo = $cod_grupo;
        $acceso->cod_ou_hab = $cod_ou_contacto;
        $acceso->nom_ou_hab = $nom_ou_contacto;
        if ($cod_persona_contacto != "")
            $acceso->cod_persona_contacto = $cod_persona_contacto;
        $acceso->nom_persona_contacto = $nom_persona_contacto;
        $acceso->ape_persona_contacto = $ape_persona_contacto;
        $acceso->json_temas = $json_temas;
        $acceso->cod_esquema_acceso = $cod_esquema_acceso;
        HabiAcceso::addAuditoria($acceso, $auditoria);
        $acceso->stm_habilitacion_hasta = $stm_habilitacion_hasta;
        $acceso->save();

        MoviPersConCred::where('cod_credencial', $cod_credencial)->orWhere('cod_persona', $cod_persona)->delete();

        DB::commit();

        Cache::forever("HabiAccesoLastUpdate", Carbon::now()->format('Y-m-d H:i:s'));


        if ($tipo_habilitacion == "P" && array_search("842383073332", $sectoresSel) !== false) {
            $request->replace(['cod_credencial' => $cod_credencial, 'ape_persona'=> $ape_persona, 'nom_persona'=>$nom_persona, 'nro_documento'=>$nro_documento, 'cod_tipo_doc'=>$cod_tipo_doc, 'cod_sexo'=>$cod_sexo, 'email'=>$email]);
            $retres = $this->sendHabtoMain($request);
            $status = $retres->status();
            if ($status == Response::HTTP_CONFLICT || $status == Response::HTTP_GONE) {
                return $retres;
            }
        }

        return response(['ok' => __('La tarjeta :COD_CREDENCIAL fue asignada a la persona :COD_PERSONA',['COD_CREDENCIAL'=>$cod_credencial,'COD_PERSONA'=>$cod_persona]) ], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request)
    {
        $temas = ConfigParametro::getTemas("LECTOR");
        $vaTemas = array();

        $cod_credencial =  preg_replace('/\D/', '', $request->input('cod_credencial'));
        $cod_credencial_nueva = preg_replace('/\D/', '', $request->input('cod_credencial_nueva'));
        $cod_persona = $request->input('cod_persona');
        $cod_ou = $request->input('cod_ou');
        $tipo_credencial = $request->input('tipo_credencial');
        $tipo_habilitacion = $request->input('tipo_habilitacion');
        $obs_habilitacion = $request->input('obs_habilitacion');
        $sectoresSel = $request->input('sectoresSel');
        $cod_esquema_acceso = $request->input('cod_esquema_acceso');
        $cod_ou_contacto = $request->input('cod_ou_contacto');
        $cod_grupo = $request->input('cod_grupo');
        $cod_persona_contacto = $request->input('cod_persona_contacto');
        $nom_persona = $request->input('nom_persona');
        $ape_persona = $request->input('ape_persona');
        $cod_sexo = $request->input('cod_sexo');
        $cod_tipo_doc = $request->input('cod_tipo_doc');
        $nro_documento = $request->input('nro_documento');
        $email = $request->input('email');
        $obs_visitas = $request->input('obs_visitas');
        $ref_credencial = "";
        $nom_persona_contacto = "";
        $ape_persona_contacto = "";
        $stm_habilitacion_hasta = null;

        $validator = Validator::make($request->all(), [
            'cod_credencial' => 'required',
            'cod_ou' => 'required',
            'tipo_habilitacion' => 'required',
            'cod_esquema_acceso' => 'required',
            'sectoresSel' => 'required'
        ], [
            'cod_credencial.required'=> __("Debe ingresar Cód. Tarjeta"),
            'cod_ou.required'=> __("Debe seleccionar Organización"),
            'tipo_habilitacion.required'=> __("Debe seleccionar Tipo Habilitación"),
            'cod_esquema_acceso.required'=> __("Debe seleccionar Esquema de Acceso"),
            'sectoresSel.required'=> __("Debe seleccionar Sectores")
        ]);
        if (!$cod_ou_contacto)
            return response(['error' => __("Debe ingresar una Organización")], Response::HTTP_CONFLICT);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $img_apto_fisico = $request->input('img_apto_fisico');
        $fec_otorgamiento_af = $request->input('fec_otorgamiento_af');

        if ($img_apto_fisico && !$fec_otorgamiento_af) {
            return response(['error'=> __("Debe ingresar Fecha Otorgamiento Apto Físico")], Response::HTTP_CONFLICT);
        }

        $stockcred = Credencial::select('tipo_habilitacion', 'ref_credencial')->where('cod_credencial', $cod_credencial)->first();
        if ($stockcred) {
            $ref_credencial = $stockcred['ref_credencial'];
            if ($stockcred['tipo_habilitacion'] != $tipo_habilitacion)
                return response(['error'=> __("Tarjeta en stock con tipo de habilitación distinta a la seleccionada")], Response::HTTP_CONFLICT);
        }

        $esq = Esquema::select()->where('cod_esquema_acceso', $cod_esquema_acceso)->first();
        if ($esq) {
            $fec_habilitacion_hasta = $esq['fec_habilitacion_hasta'];
            $stm_actual = Carbon::now()->format('Y-m-d H:i:s');
            if ($fec_habilitacion_hasta < $stm_actual && (int) $fec_habilitacion_hasta != 0) {
                return response(['error'=> __("Esquema habilitado hasta :FEC_HABILITACION_HASTA",['FEC_HABILITACION_HASTA'=>$fec_habilitacion_hasta])], Response::HTTP_CONFLICT);
            }
        }

        if ($tipo_habilitacion == "T") {

            $stockcred = Credencial::select('tipo_habilitacion')->where('cod_credencial', $cod_credencial)->first();
            if (!$stockcred) {
                return response(['error'=> __("Tarjeta inexistente en Stock Tarjetas")], Response::HTTP_CONFLICT);
            } else if ($stockcred['tipo_habilitacion'] != "T") {
                return response(['error'=> __("La tarjeta ingresada debe ser de tipo Temporal/Visita")], Response::HTTP_CONFLICT);
            }

            $tipo_credencial = "RFID";
            $cod_persona_contacto = $request->input('cod_persona_contacto');
            if (!$cod_persona_contacto)
                return response(['error'=> __("Debe ingresar Persona Contacto")], Response::HTTP_CONFLICT);

            $valida_nrodoc_visitas_unico = ConfigParametro::get('VALIDA_NRODOC_VISITAS_UNICO', false);
            if ($valida_nrodoc_visitas_unico) {
                $existe = habiCredPersona::select('habiCredPersona.tipo_habilitacion')
                    ->join('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
                    ->where('habiCredPersona.tipo_habilitacion', $tipo_habilitacion)
                    ->where('maesPersonas.nro_documento', $request->input('nro_documento'))->first();

                if ($existe) {
                    return response(['error'=> __("Ya existe una habilitación temporal con el número de DNI indicado")], Response::HTTP_CONFLICT);
                }
            }
        } else {
            $cod_persona_contacto = "";
        }

        if (!$cod_persona || $cod_persona == "") {
            $persona = Persona::select()->where('nro_documento', $request->input('nro_documento'))->first();
            if ($persona) {
                $auditoria = "M";
                $cod_persona = $persona->cod_persona;
            } else {
                $persona = new Persona;
                $cod_persona = Persona::getUuid();
                $persona->cod_persona = $cod_persona;
                $persona->ind_bloqueo = 0;
                $auditoria = "A";
            }
        } else {
            $persona = Persona::find($cod_persona);
            $persona->nro_documento_ant = $persona->nro_documento; //Grabo el nro documento anterior por si se modificó
            $auditoria = "M";
        }

        if ($persona->ind_bloqueo == "1") {
            return response(['error'=> __("DNI bloqueado. Motivo :DES_MOTIVO_BLOQUEO",['DES_MOTIVO_BLOQUEO'=> $persona->des_motivo_bloqueo])], Response::HTTP_CONFLICT);
        }

        $persona->nom_persona = $nom_persona;
        $persona->ape_persona = $ape_persona;
        $persona->cod_sexo = $cod_sexo;
        $persona->cod_tipo_doc = $cod_tipo_doc;
        $persona->nro_documento = $nro_documento;
        $persona->email = $email;
        $persona->obs_visitas = $obs_visitas;
        Persona::addAuditoria($persona, $auditoria);
        $persona->save();

        if ($request->input('img_persona')) {
            $auditoria = "M";
            $imagenes = Imagen::select()->where('cod_persona', $cod_persona)->first();
            if (!$imagenes) {
                $imagenes = new Imagen;
                $imagenes->cod_persona = $cod_persona;
                $auditoria = "A";
            }
            $imagenes->img_persona = $request->input('img_persona');
            Imagen::addAuditoria($imagenes, $auditoria);
            $imagenes->save();
        }

        if ($img_apto_fisico) {

            $plazo_vigencia_apto_fisico = ConfigParametro::get('PLAZO_VIGENCIA_APTO_FISICO', false);
            if (!$plazo_vigencia_apto_fisico) {
                $plazo_vigencia_apto_fisico = "1Y";
            }
            $fec_vencimiento_af = Persona::addDateDiff($plazo_vigencia_apto_fisico, $fec_otorgamiento_af);

            $auditoria = "M";
            $apto = AptoFisico::select()->where('cod_persona', $cod_persona)->first();
            if (!$apto) {
                $apto = new AptoFisico;
                $apto->cod_persona = $cod_persona;
                $auditoria = "A";
            }
            $apto->img_apto_fisico = $img_apto_fisico;
            $apto->fec_otorgamiento_af = $fec_otorgamiento_af;
            $apto->fec_vencimiento_af = $fec_vencimiento_af;
            $apto->stm_notificacion = null;
            AptoFisico::addAuditoria($apto, $auditoria);
            $apto->save();
        }

        $auditoria = "M";
        $habilitacion_persona = HabiCredPersona::find($cod_credencial);
        if (!$habilitacion_persona) {
            $habilitacion_persona = new HabiCredPersona;
            $auditoria = "A";
        }
        $habilitacion_persona->cod_credencial = $cod_credencial;
        $habilitacion_persona->cod_persona = $cod_persona;
        $habilitacion_persona->tipo_credencial = $tipo_credencial;
        $habilitacion_persona->tipo_habilitacion = $tipo_habilitacion;
        $habilitacion_persona->obs_habilitacion = $obs_habilitacion;
        HabiCredPersona::addAuditoria($habilitacion_persona, $auditoria);
        $habilitacion_persona->cod_esquema_acceso = $cod_esquema_acceso;
        $habilitacion_persona->cod_ou_emisora = $cod_ou;
        $habilitacion_persona->cod_ou_hab = $cod_ou_contacto;
        $habilitacion_persona->stm_habilitacion_desde = $habilitacion_persona->aud_stm_ingreso;
        $habilitacion_persona->stm_habilitacion_hasta = $stm_habilitacion_hasta;
        $habilitacion_persona->save();

        HabiCredSectores::where('cod_credencial', $cod_credencial)->delete();
        foreach ($sectoresSel as $cod_sector) {
            $habilitacion_sectores = new HabiCredSectores;
            $habilitacion_sectores->cod_credencial = $cod_credencial;
            $habilitacion_sectores->cod_ou = $cod_ou;
            $habilitacion_sectores->cod_sector = $cod_sector;
            HabiCredSectores::addAuditoria($habilitacion_sectores, "A");
            $habilitacion_sectores->save();

            foreach ($temas as $cod_tema => $datos_tema) {
                if ($datos_tema['cod_sector'] == $cod_sector) {
                    $vaTemas[$cod_tema] = $cod_tema;
                }
            }
        }

        if ($cod_grupo) {
            HabiCredGrupo::where('cod_credencial', $cod_credencial)->delete();
            $habilitacion_grupo = new HabiCredGrupo;
            $habilitacion_grupo->cod_credencial = $cod_credencial;
            $habilitacion_grupo->cod_grupo = $cod_grupo;
            HabiCredGrupo::addAuditoria($habilitacion_grupo, "RL");
            $habilitacion_grupo->save();
        } else {
            $cod_grupo = 0;
        }

        //GRABA EN HABIACCESO
        $json_temas = $vaTemas;

        $ou = UnidadesOrganiz::select()->where('cod_ou', $cod_ou)->first();
        $nom_ou = $ou['nom_ou'];

        $ou = UnidadesOrganiz::select()->where('cod_ou', $cod_ou_contacto)->first();
        $nom_ou_contacto = $ou['nom_ou'];
        if ($cod_persona_contacto != "") {
            $persona_contacto = Persona::select()->where('cod_persona', $cod_persona_contacto)->first();
            $nom_persona_contacto = $persona_contacto['nom_persona'];
            $ape_persona_contacto = $persona_contacto['ape_persona'];
        }

        $auditoria = "RL";
        $acceso = HabiAcceso::find($cod_credencial);
        if (!$acceso) {
            $acceso = new HabiAcceso;
        }
        $acceso->cod_credencial = $cod_credencial;
        $acceso->cod_ou = $cod_ou;
        $acceso->nom_ou = $nom_ou;
        $acceso->ref_credencial = $ref_credencial;
        $acceso->cod_persona = $cod_persona;
        $acceso->nom_persona = $nom_persona;
        $acceso->ape_persona = $ape_persona;
        $acceso->cod_sexo = $cod_sexo;
        $acceso->cod_tipo_doc = $cod_tipo_doc;
        $acceso->nro_documento = $nro_documento;
        $acceso->tipo_habilitacion = $tipo_habilitacion;
        $acceso->obs_habilitacion = $obs_habilitacion;
        $acceso->cod_grupo = $cod_grupo;
        $acceso->cod_ou_contacto = $cod_ou_contacto;
        $acceso->nom_ou_contacto = $nom_ou_contacto;
        $acceso->cod_persona_contacto = $cod_persona_contacto;
        $acceso->nom_persona_contacto = $nom_persona_contacto;
        $acceso->ape_persona_contacto = $ape_persona_contacto;
        $acceso->json_temas = $json_temas;
        $acceso->cod_esquema_acceso = $cod_esquema_acceso;
        HabiAcceso::addAuditoria($acceso, $auditoria);
        $acceso->stm_habilitacion = $acceso->aud_stm_ingreso;
        $acceso->stm_habilitacion_hasta = $stm_habilitacion_hasta;
        $acceso->save();

        MoviPersConCred::where('cod_credencial', $cod_credencial)->orWhere('cod_persona', $cod_persona)->delete();

        Cache::forever("HabiAccesoLastUpdate", Carbon::now()->format('Y-m-d H:i:s'));


        return response(['ok' => __('La tarjeta :COD_CREDENCIAL fue asignada a la persona :COD_PERSONA',['COD_CREDENCIAL'=>$cod_credencial,'COD_PERSONA'=>$cod_persona])], Response::HTTP_OK);
    }

    /**
     * Actualiza nueva tarjeta 
     */
    private function frecambioTarjeta($cod_credencial_nueva, $cod_credencial)
    {
        $habilitacion_persona = HabiCredPersona::find($cod_credencial_nueva);
        if ($habilitacion_persona)
            return response(['error' => __('La tarjeta :COD_CREDENCIAL_NUEVA corresponde a otra persona',['COD_CREDENCIAL_NUEVA'=>$cod_credencial_nueva])], Response::HTTP_CONFLICT);

        $habiCredPersona = HabiCredPersona::find($cod_credencial);
        if ($habiCredPersona) {
            $habiCredPersona->cod_credencial = $cod_credencial_nueva;
            $habiCredPersona->save();
        }

        $habiCredGrupo = HabiCredGrupo::find($cod_credencial);
        if ($habiCredGrupo) {
            $habiCredGrupo->cod_credencial = $cod_credencial_nueva;
            $habiCredGrupo->save();
        }

        HabiAcceso::where('cod_credencial', $cod_credencial)->delete();

        return response(['ok'=> __("Cód. Tarjeta reasignado :COD_CREDENCIAL_NUEVA",['COD_CREDENCIAL_NUEVA'=>$cod_credencial_nueva]) . $cod_credencial_nueva], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true);
        $cod_credencial = $clave[0][0];
        $cod_ou = $clave[0][1];

        HabiCredSectores::where('cod_credencial', $cod_credencial)->delete();
        HabiCredPersona::where('cod_credencial', $cod_credencial)->delete();
        HabiAcceso::where('cod_credencial', $cod_credencial)->delete();
        try {
            HabiCredGrupo::where('cod_credencial', $cod_credencial)->delete();
        } catch (Exception $e) {
        };

        Cache::forever("HabiAccesoLastUpdate", Carbon::now()->format('Y-m-d H:i:s'));

        return response(['ok' => __('Se eliminó satisfactoriamente la tarjeta :COD_CREDENCIAL',['COD_CREDENCIAL'=>$cod_credencial])], Response::HTTP_OK);
    }

    public function upload()
    {

        //$filename = $_FILES['file']['name'];
        $filePath = storage_path('files'); //crear carpeta file
        if (!is_dir($filePath)) {
            if (!mkdir($filePath, 0777, true)) {
                return response(['error'=> __("No se pudo adjuntar archivo")], Response::HTTP_CONFLICT);
            }
        }

        if (empty($_FILES['file'])) {
            return response(['error'=> __("No se pudo adjuntar archivo")], Response::HTTP_CONFLICT);
        }

        $import_hash = hash_file("sha256", $_FILES['file']['tmp_name']);
        $destination = $filePath . DIRECTORY_SEPARATOR . $import_hash;
        move_uploaded_file($_FILES['file']['tmp_name'], $destination);

        return response(['ok'=> __("Se importó satisfactoriamente el archivo"), 'import_hash' => $import_hash], Response::HTTP_OK);
    }

    public function getSexo($sexo)
    {
        switch (strtoupper($sexo)) {
            case 'M':
                # code...
                return "M";
                break;
            case 'F':
                # code...
                return "F";
                break;

            default:
                return "NI";
                break;
        }
    }
    public function getTipoDoc($tipodoc)
    {
        switch (strtoupper($tipodoc)) {
            case 'DNI':
                # code...
                return "DNI";
                break;
            case 'PAS':
                # code...
                return "PAS";
                break;

            default:
                return "DNI";
                break;
        }
    }
    public function importa(Request $request)
    {
        $import_hash = $request->input('import_hash');
        $filePath = storage_path('files'); //crear carpeta file
        $filetoImport = $filePath . "/" . $import_hash;

        if ($import_hash == "")
            return response(['error'=> __("No se seleccionó archivo")], Response::HTTP_CONFLICT);

        if (!file_exists($filetoImport)) {
            return response(['error'=> __("No se pudo adjuntar archivo")], Response::HTTP_CONFLICT);
        }

        $nro_documento_contador = Persona::max('nro_documento');
        if ($nro_documento_contador < 100000000)
            $nro_documento_contador = 100000000;
        $nro_documento_contador++;
        //        $cod_credencial = $request->input('cod_credencial');
        //        $cod_credencial_nueva = $request->input('cod_credencial_nueva');
        //        $cod_persona = $request->input('cod_persona');
        $cod_ou = $request->input('cod_ou');
        $sectoresSel = $request->input('sectoresSel');
        $tipo_credencial = $request->input('tipo_credencial');
        $tipo_habilitacion = $request->input('tipo_habilitacion');
        //        $obs_habilitacion = $request->input('obs_habilitacion');
        $cod_esquema_acceso = $request->input('cod_esquema_acceso');
        $cod_ou_contacto = $request->input('cod_ou_contacto');
        //        $cod_grupo = $request->input('cod_grupo');
        //        $cod_persona_contacto = $request->input('cod_persona_contacto');
        //        $nro_documento = $request->input('nro_documento');
        //      $email = $request->input('email');
        //        $obs_visitas = $request->input('obs_visitas');


        $table = new Table($filetoImport);

        while ($record = $table->nextRecord()) {
            $apenom = mb_convert_encoding($record->nombre, "UTF-8");
            $strpos = strpos($apenom, ',');
            $ape_persona =  trim(substr($apenom, 0, $strpos));
            $nom_persona = trim(substr($apenom, $strpos + 1));
            $cod_sexo = $this->getSexo($record->sexo);
            $cod_tipo_doc = $this->getTipoDoc($record->tipo);
            $nro_documento = trim($record->n_mero);
            if ($nro_documento == "" || $nro_documento == "0") {
                $nro_documento = $nro_documento_contador;
                $nro_documento_contador++;
            }

            $cod_credencial = trim(str_replace("-", "", $record->tarjeta));


            $rowRequest = new \Illuminate\Http\Request();
            $rowRequest->setMethod('POST');
            $rowRequest->request->add(['ape_persona' => $ape_persona]);
            $rowRequest->request->add(['nom_persona' => $nom_persona]);
            $rowRequest->request->add(['cod_sexo' => $cod_sexo]);
            $rowRequest->request->add(['cod_tipo_doc' => $cod_tipo_doc]);
            $rowRequest->request->add(['cod_credencial' => $cod_credencial]);
            $rowRequest->request->add(['nro_documento' => $nro_documento]);
            $rowRequest->request->add(['tipo_credencial' => $tipo_credencial]);
            $rowRequest->request->add(['tipo_habilitacion' => $tipo_habilitacion]);
            $rowRequest->request->add(['sectoresSel' => $sectoresSel]);
            $rowRequest->request->add(['cod_esquema_acceso' => $cod_esquema_acceso]);
            $rowRequest->request->add(['cod_ou' => $cod_ou]);
            $rowRequest->request->add(['cod_ou_contacto' => $cod_ou_contacto]);
            $rowRequest->request->add(['email' => ""]);
            $res = $this->store($rowRequest);
            unset($rowRequest);
            //            file_put_contents("C:/temp/salida.txt", "$ape_persona|$nom_persona|$cod_sexo|$cod_tipo_doc|$nro_documento|$cod_credencial| " .var_Export($res,true) . "\n", FILE_APPEND);
            //    echo $record->my_column;
        }


        //        return response(['error'=> __("Debug :TIPO_CREDENCIAL",['TIPO_CREDENCIAL'=>$tipo_credencial]) ], Response::HTTP_CONFLICT);

        return response(['ok'=> __("Se importó satisfactoriamente el archivo")], Response::HTTP_OK);
    }

    public function storeOU(Request $request)
    {
        $cod_tipo_doc = $request->input('cod_tipo_doc');
        $nro_documento = $request->input('nro_documento');
        $nom_persona = $request->input('nom_persona');
        $ape_persona = $request->input('ape_persona');
        $cod_sexo = $request->input('cod_sexo');
        $email = $request->input('email');
        $cod_persona = "";

        $cod_credencial = preg_replace('/\D/', '', $request->input('cod_credencial'));
        $cod_ou = 43; //$request->input('cod_ou');
        $cod_ou_contacto = 43; //$request->input('cod_ou');
        $sectoresSel = ["103407960471", "512426410818", "754967082268"];
        $tipo_credencial = "RFID";
        $tipo_habilitacion = "P";
        $cod_esquema_acceso = "GENERAL";

        $habilitacion_persona = HabiCredPersona::find($cod_credencial);
        if ($habilitacion_persona) {
            return response(['ok'=> __("Credencial ya habilitada")], Response::HTTP_OK);
        }

        $existe = Persona::select('maesPersonas.cod_persona', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona', 'maesPersonas.cod_sexo', 'maesPersonas.email')
            ->where('maesPersonas.cod_tipo_doc', $cod_tipo_doc)
            ->where('maesPersonas.nro_documento', $nro_documento)->first();

        if ($existe) {
            $cod_persona = $existe->cod_persona;
            $nom_persona = $existe->nom_persona;
            $ape_persona = $existe->ape_persona;
            $cod_sexo = $existe->cod_sexo;
            $email = $existe->email;
        }

        $rowRequest = new \Illuminate\Http\Request();
        $rowRequest->setMethod('POST');
        $rowRequest->request->add(['cod_credencial' => $cod_credencial]);
        $rowRequest->request->add(['cod_credencial_nueva' => $cod_credencial]);
        $rowRequest->request->add(['tipo_credencial' => $tipo_credencial]);
        $rowRequest->request->add(['tipo_habilitacion' => $tipo_habilitacion]);
        $rowRequest->request->add(['sectoresSel' => $sectoresSel]);
        $rowRequest->request->add(['cod_esquema_acceso' => $cod_esquema_acceso]);
        $rowRequest->request->add(['cod_ou' => $cod_ou]);
        $rowRequest->request->add(['cod_ou_contacto' => $cod_ou_contacto]);
        $rowRequest->request->add(['cod_persona' => $cod_persona]);
        $rowRequest->request->add(['nom_persona' => $nom_persona]);
        $rowRequest->request->add(['ape_persona' => $ape_persona]);
        $rowRequest->request->add(['cod_sexo' => $cod_sexo]);
        $rowRequest->request->add(['email' => $email]);
        $rowRequest->request->add(['cod_tipo_doc' => $cod_tipo_doc]);
        $rowRequest->request->add(['nro_documento' => $nro_documento]);

        return $this->store($rowRequest);
        //        unset($rowRequest);
    }
}
