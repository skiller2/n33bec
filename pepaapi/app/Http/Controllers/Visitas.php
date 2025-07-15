<?php

namespace App\Http\Controllers;

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
use App\Usuario;
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
use function auth;
use function response;
use function storage_path;
use App\Traits\Libgeneral;


class Visitas extends Controller
{
    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "store":
            case "update":
            case "delete":
            case "gridOptions":
            case "detalle":
                return "ab_gestion_visitas";
            default:
                return "";
        }
    }

    public function getDefaults()
    {
        $cod_usuario = auth()->user()['cod_usuario'];
        $query = Usuario::select('sector_default', 'esquema_default')->where('cod_usuario', $cod_usuario)->first();
        $sector_default = ($query['sector_default']) ? $query['sector_default'] : "";
        $esquema_default = ($query['esquema_default']) ? $query['esquema_default'] : "";
        $ind_visita_simplificada = ($query['ind_visita_simplificada']) ? $query['$ind_visita_simplificada'] : false;
        return array(
            "sector_default" => $sector_default,
            "esquema_default" => $esquema_default,
            "ind_visita_simplificada" => $ind_visita_simplificada
        );
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request, $export)
    {
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
        $query = HabiCredPersona::select(
            'habiCredPersona.cod_credencial',
            'habiCredPersona.cod_persona',
            'habiCredPersona.tipo_credencial',
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
            DB::raw('GROUP_CONCAT(maesSectores.nom_sector) as nom_sector'),
            'maesAliasCred.ref_credencial',
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
            ->leftjoin('maesAliasCred', 'maesAliasCred.cod_credencial', '=', 'habiCredPersona.cod_credencial');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($nombre == "ref_credencial") {
                        $operacion = "=";
                        if (is_numeric($valor)) {
                            $valor = (int)$valor;
                            $valor = (string)$valor;
                        }
                    } else if ($nombre == "aud_stm_ingreso") {
                        $nombre = "stm_habilitacion_desde";
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
        $query->where("habiCredPersona.cod_ou_emisora", $cod_ou)
            ->groupBy('habiCredPersona.cod_credencial', 'habiCredPersona.cod_ou_emisora', 'habiCredPersona.cod_persona_contacto', 'habiCredPersona.cod_ou_hab', 'habiCredPersona.cod_persona', 'habiCredPersona.tipo_habilitacion', 'habiCredPersona.tipo_credencial', 'habiCredGrupo.cod_grupo', 'confGrupoCred.des_grupo', 'maesPersonas.nro_documento', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona', 'habiCredPersona.cod_esquema_acceso', 'habiCredPersona.stm_habilitacion_desde', 'habiCredPersona.stm_habilitacion_hasta', 'maesUnidadesOrganiz.nom_ou', 'maesAliasCred.ref_credencial');

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

            $fileName = "Habilitaciones_Visitas.$typeExp";
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
            case "des_grupo":
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
                $columnDefs[] = array("prop" => "cod_credencial", "name" => "Cód. Tarjeta", "key" => "cod_credencial");
                $columnDefs[] = array("prop" => "ref_credencial", "name" => "Ref. Tarjeta");
                $columnDefs[] = array("prop" => "nom_ou", "name" => "Organización");
                $columnDefs[] = array("prop" => "cod_ou_emisora", "name" => "OU", "visible" => false, "key" => "cod_ou_emisora");
                $columnDefs[] = array("prop" => "nom_sector", "name" => "Sectores", "pipe" => "ftSectores");
                $columnDefs[] = array("prop" => "nro_documento", "name" => "DNI");
                $columnDefs[] = array("prop" => "ape_persona", "name" => "Apellido");
                $columnDefs[] = array("prop" => "nom_persona", "name" => "Nombre");
                $columnDefs[] = array("prop" => "stm_habilitacion_desde", "name" => "Fecha Desde", "pipe" => "ftDateTime");
                $columnDefs[] = array("prop" => "stm_habilitacion_hasta", "name" => "Fecha Hasta", "pipe" => "ftDateTime");
                break;
            default:
                $columnDefs[] = array("field" => "cod_credencial", "displayName" => "Cód. Tarjeta", "cellFilter" => "ftTarjeta");
                $columnDefs[] = array("field" => "ref_credencial", "displayName" => "Ref. Tarjeta");
                $columnDefs[] = array("field" => "nom_ou", "displayName" => "Organización");
                $columnDefs[] = array("field" => "cod_ou", "displayName" => "cod_ou", "visible" => false);
                $columnDefs[] = array("field" => "nom_sector", "displayName" => "Sectores");
                $columnDefs[] = array("field" => "nro_documento", "displayName" => "DNI");
                $columnDefs[] = array("field" => "ape_persona", "displayName" => "Apellido");
                $columnDefs[] = array("field" => "nom_persona", "displayName" => "Nombre");
                $columnDefs[] = array("field" => "stm_habilitacion_desde", "displayName" => "Fecha Desde", "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "stm_habilitacion_hasta", "displayName" => "Fecha Hasta", "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['cod_credencial', 'cod_ou'];

        $filtros[] = array('id' => 'cod_credencial', 'name' => 'Cód. Tarjeta');
        $filtros[] = array('id' => 'ref_credencial', 'name' => 'Ref. Tarjeta');
        $filtros[] = array('id' => 'nom_ou', 'name' => 'Organización');
        $filtros[] = array('id' => 'nom_sector', 'name' => 'Sectores');
        $filtros[] = array('id' => 'nro_documento', 'name' => 'Nro. Documento');
        $filtros[] = array('id' => 'des_persona', 'name' => 'Apellido y Nombre');

        $rango['desde'] = array('id' => 'stm_habilitacion_desde', 'tipo' => 'datetime');
        $rango['hasta'] = array('id' => 'stm_habilitacion_desde', 'tipo' => 'datetime');

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
            return response(['error' => 'Debe seleccionar Unidad Organizacional'], Response::HTTP_CONFLICT);

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
        $ind_movimiento = "";
        $stm_habilitacion = "";
        $des_persona_contacto = "";
        $obs_habilitacion = "";
        $obs_visitas = "";
        $vaSectores = array();

        $query = HabiCredPersona::select(
            'habiCredPersona.cod_credencial',
            'habiCredPersona.cod_persona',
            'habiCredPersona.tipo_credencial',
            'habiCredPersona.tipo_habilitacion',
            'habiCredPersona.cod_ou_emisora',
            'habiCredPersona.cod_persona_contacto',
            'habiCredPersona.cod_ou_hab',
            'habiCredPersona.tipo_habilitacion',
            'habiCredGrupo.cod_grupo',
            'habiCredPersona.obs_habilitacion',
            'confGrupoCred.des_grupo',
            'maesUnidadesOrganiz.nom_ou',
            'maesPersonas.nro_documento',
            'maesPersonas.nom_persona',
            'maesPersonas.ape_persona',
            'maesPersonas.obs_visitas',
            'habiCredSectores.cod_sector',
            //DB::raw('GROUP_CONCAT(maesSectores.nom_sector) as nom_sector'), 
            'habiCredPersona.cod_esquema_acceso',
            'habiCredPersona.stm_habilitacion_desde',
            'habiCredPersona.stm_habilitacion_hasta'
        )
            ->leftjoin('habiCredGrupo', 'habiCredGrupo.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('confGrupoCred', 'confGrupoCred.cod_grupo', '=', 'habiCredGrupo.cod_grupo')
            ->leftjoin('habiCredSectores', 'habiCredSectores.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('maesSectores', 'maesSectores.cod_sector', '=', 'habiCredSectores.cod_sector')
            ->leftjoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
            ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona_contacto')
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
            $ind_movimiento = $row['ind_movimiento'];
            $stm_habilitacion = $row['stm_habilitacion'];
            $obs_visitas = $row['obs_visitas'];
            foreach ($query as $row) {
                if ($row['cod_sector'])
                    $vaSectores[] = $row['cod_sector'];
            }
        }
        return array(
            "cod_ou"=> $cod_ou, "cod_credencial" => $cod_credencial, "cod_persona_contacto" => $cod_persona_contacto, "des_persona_contacto" => $des_persona_contacto, "obs_habilitacion" => $obs_habilitacion,
            "tipo_credencial" => $tipo_credencial, "tipo_habilitacion" => $tipo_habilitacion, "ind_movimiento" => $ind_movimiento, "stm_habilitacion" => $stm_habilitacion,
            "cod_esquema_acceso" => $cod_esquema_acceso, "stm_habilitacion_desde" => $stm_habilitacion_desde, "stm_habilitacion_hasta" => $stm_habilitacion_hasta,
            "sectoresSel" => $vaSectores, "cod_persona" => $cod_persona, "cod_grupo" => $cod_grupo, "cod_ou_contacto" => $cod_ou_contacto, "obs_visitas"=>$obs_visitas
        );
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

        $cod_credencial = $request->input('cod_credencial');
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

        $validator = Validator::make(
            $request->all(),
            [
                'cod_credencial' => 'required',
                'cod_ou' => 'required',
                'tipo_habilitacion' => 'required',
                'cod_esquema_acceso' => 'required',
                'sectoresSel' => 'required',
                'cod_tipo_doc' => 'required',
                'nro_documento' => 'required',
                'nom_persona' => 'required',
                'ape_persona' => 'required'
            ],

            [
                'cod_credencial.required' => "Debe ingresar Cód. Tarjeta",
                'cod_ou.required' => "Debe seleccionar Organización",
                'tipo_habilitacion.required' => "Debe seleccionar Tipo Habilitación",
                'cod_esquema_acceso.required' => "Debe seleccionar Esquema de Acceso",
                'sectoresSel.required' => "Debe seleccionar Sectores",
                'cod_tipo_doc.required'  => "Debe seleccionar tipo documento",
                'nro_documento.required' => "Debe ingresar número de documento",
                'nom_persona.required'  => "Ingrese nombre de la visita",
                'ape_persona.required' => "Ingrese apellido de la visita"

            ]
        );

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $stockcred = Credencial::select('tipo_habilitacion', 'ref_credencial')->where('cod_credencial', $cod_credencial)->first();
        if ($stockcred) {
            $ref_credencial = $stockcred['ref_credencial'];
            if ($stockcred['tipo_habilitacion'] != $tipo_habilitacion)
                return response(['error' => "Tarjeta en stock con tipo de habilitación distinta a la seleccionada"], Response::HTTP_CONFLICT);
        }

        $esq = Esquema::select()->where('cod_esquema_acceso', $cod_esquema_acceso)->first();
        if ($esq) {
            $fec_habilitacion_hasta = $esq['fec_habilitacion_hasta'];
            $stm_actual = Carbon::now()->format('Y-m-d H:i:s');
            if ($fec_habilitacion_hasta < $stm_actual && (int) $fec_habilitacion_hasta != 0) {
                return response(['error' => "Esquema habilitado hasta $fec_habilitacion_hasta"], Response::HTTP_CONFLICT);
            }
        }

        if ($tipo_habilitacion == "T") {

            $stockcred = Credencial::select('tipo_habilitacion')->where('cod_credencial', $cod_credencial)->first();
            if (!$stockcred) {
                return response(['error' => "Tarjeta inexistente en Stock Tarjetas"], Response::HTTP_CONFLICT);
            } else if ($stockcred['tipo_habilitacion'] != "T") {
                return response(['error' => "La tarjeta ingresada debe ser de tipo Temporal/Visita"], Response::HTTP_CONFLICT);
            }

            $tipo_credencial = "RFID";
            if (!$cod_ou_contacto)
                return response(['error' => 'Debe ingresar Organización'], Response::HTTP_CONFLICT);
            $cod_persona_contacto = $request->input('cod_persona_contacto');
            if (!$cod_persona_contacto)
                return response(['error' => 'Debe ingresar Persona Contacto'], Response::HTTP_CONFLICT);

            $valida_nrodoc_visitas_unico = ConfigParametro::get('VALIDA_NRODOC_VISITAS_UNICO', false);

            if ($valida_nrodoc_visitas_unico) {
                $existe = habiCredPersona::select('habiCredPersona.tipo_habilitacion')
                    ->join('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
                    ->where('habiCredPersona.tipo_habilitacion', $tipo_habilitacion)
                    ->where('maesPersonas.nro_documento', $request->input('nro_documento'))->first();

                if ($existe) {
                    return response(['error' => 'Ya existe una habilitación temporal con el número de DNI indicado'], Response::HTTP_CONFLICT);
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
            return response(['error' => 'DNI bloqueado. Motivo: ' . $persona->des_motivo_bloqueo], Response::HTTP_CONFLICT);
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

        if ($request->input('img_documento')) {
            $auditoria = "M";
            $imagenes = Imagen::select()->where('cod_persona', $cod_persona)->first();
            if (!$imagenes) {
                $imagenes = new Imagen;
                $imagenes->cod_persona = $cod_persona;
                $auditoria = "A";
            }
            $imagenes->img_documento = $request->input('img_documento');
            Imagen::addAuditoria($imagenes, $auditoria);
            $imagenes->save();
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
        $habilitacion_persona->cod_persona_contacto = $cod_persona_contacto;
        HabiCredPersona::addAuditoria($habilitacion_persona, $auditoria);
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
        $acceso->cod_persona_contacto = $cod_persona_contacto;
        $acceso->nom_persona_contacto = $nom_persona_contacto;
        $acceso->ape_persona_contacto = $ape_persona_contacto;
        $acceso->json_temas = $json_temas;
        $acceso->cod_esquema_acceso = $cod_esquema_acceso;
        HabiAcceso::addAuditoria($acceso, $auditoria);
        $acceso->stm_habilitacion_hasta = $stm_habilitacion_hasta;
        $acceso->save();

        MoviPersConCred::where('cod_credencial', $cod_credencial)->orWhere('cod_persona', $cod_persona)->delete();

        Cache::forever("HabiAccesoLastUpdate", Carbon::now()->format('Y-m-d H:i:s'));

        return response(['ok' => 'La tarjeta ' . $cod_credencial . ' fue asignada a la persona: ' . $cod_persona], Response::HTTP_OK);
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

        $cod_credencial = $request->input('cod_credencial');
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
            'sectoresSel' => 'required',
            'cod_tipo_doc' => 'required',
            'nro_documento' => 'required',
            'nom_persona' => 'required',
            'ape_persona' => 'required'
        ], [
            'cod_credencial.required' => "Debe ingresar Tarjeta",
            'cod_ou.required' => "Debe seleccionar Organización",
            'tipo_habilitacion.required' => "Debe seleccionar Tipo Habilitación",
            'cod_esquema_acceso.required' => "Debe seleccionar Esquema de Acceso",
            'sectoresSel.required' => "Debe seleccionar Sectores",
            'cod_tipo_doc.required'  => "Debe seleccionar tipo documento",
            'nro_documento.required' => "Debe ingresar número de documento",
            'nom_persona.required'  => "Ingrese nombre de la visita",
            'ape_persona.required' => "Ingrese apellido de la visita"
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $stockcred = Credencial::select('tipo_habilitacion', 'ref_credencial')->where('cod_credencial', $cod_credencial)->first();
        if ($stockcred) {
            $ref_credencial = $stockcred['ref_credencial'];
            if ($stockcred['tipo_habilitacion'] != $tipo_habilitacion)
                return response(['error' => "Tarjeta en stock con tipo de habilitación distinta a la seleccionada"], Response::HTTP_CONFLICT);
        }

        $esq = Esquema::select()->where('cod_esquema_acceso', $cod_esquema_acceso)->first();
        if ($esq) {
            $fec_habilitacion_hasta = $esq['fec_habilitacion_hasta'];
            $stm_actual = Carbon::now()->format('Y-m-d H:i:s');
            if ($fec_habilitacion_hasta < $stm_actual && (int) $fec_habilitacion_hasta != 0) {
                return response(['error' => "Esquema habilitado hasta $fec_habilitacion_hasta"], Response::HTTP_CONFLICT);
            }
        }

        if ($tipo_habilitacion == "T") {

            $stockcred = Credencial::select('tipo_habilitacion')->where('cod_credencial', $cod_credencial)->first();
            if (!$stockcred) {
                return response(['error' => "Tarjeta inexistente en Stock Tarjetas"], Response::HTTP_CONFLICT);
            } else if ($stockcred['tipo_habilitacion'] != "T") {
                return response(['error' => "La tarjeta ingresada debe ser de tipo Temporal/Visita"], Response::HTTP_CONFLICT);
            }

            $tipo_credencial = "RFID";
            if (!$cod_ou_contacto)
                return response(['error' => 'Debe ingresar Organización'], Response::HTTP_CONFLICT);
            $cod_persona_contacto = $request->input('cod_persona_contacto');
            if (!$cod_persona_contacto)
                return response(['error' => 'Debe ingresar Persona Contacto'], Response::HTTP_CONFLICT);

            $valida_nrodoc_visitas_unico = ConfigParametro::get('VALIDA_NRODOC_VISITAS_UNICO', false);
            if ($valida_nrodoc_visitas_unico) {
                $existe = habiCredPersona::select('habiCredPersona.tipo_habilitacion')
                    ->join('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
                    ->where('habiCredPersona.tipo_habilitacion', $tipo_habilitacion)
                    ->where('maesPersonas.nro_documento', $request->input('nro_documento'))
                    ->whereNotIn('habiCredPersona.cod_credencial', [$cod_credencial])
                    ->first();

                if ($existe) {
                    return response(['error' => 'Ya existe una habilitación temporal con el número de DNI indicado'], Response::HTTP_CONFLICT);
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

        if ($request->input('img_documento')) {
            $auditoria = "M";
            $imagenes = Imagen::select()->where('cod_persona', $cod_persona)->first();
            if (!$imagenes) {
                $imagenes = new Imagen;
                $imagenes->cod_persona = $cod_persona;
                $auditoria = "A";
            }
            $imagenes->img_documento = $request->input('img_documento');
            Imagen::addAuditoria($imagenes, $auditoria);
            $imagenes->save();
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
        $acceso->cod_persona_contacto = $cod_persona_contacto;
        $acceso->nom_persona_contacto = $nom_persona_contacto;
        $acceso->ape_persona_contacto = $ape_persona_contacto;
        $acceso->json_temas = $json_temas;
        $acceso->cod_esquema_acceso = $cod_esquema_acceso;
        HabiAcceso::addAuditoria($acceso, $auditoria);
        $acceso->stm_habilitacion_hasta = $stm_habilitacion_hasta;
        $acceso->save();


        MoviPersConCred::where('cod_credencial', $cod_credencial)->orWhere('cod_persona', $cod_persona)->delete();

        Cache::forever("HabiAccesoLastUpdate", Carbon::now()->format('Y-m-d H:i:s'));

        return response(['ok' => 'La tarjeta ' . $cod_credencial . ' fue asignada a la persona: ' . $cod_persona], Response::HTTP_OK);
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

        $habiCredPersona = HabiCredPersona::select('cod_persona')->where('cod_credencial', $cod_credencial)->first();
        $cod_persona = $habiCredPersona['cod_persona'];

        HabiCredPersona::where('cod_credencial', $cod_credencial)->delete();
        HabiCredSectores::where('cod_credencial', $cod_credencial)->delete();
        HabiAcceso::where('cod_credencial', $cod_credencial)->delete();
        try {
            HabiCredGrupo::where('cod_credencial', $cod_credencial)->delete();
        } catch (Exception $e) {
        }

        if ($cod_persona) {
            $moviPersConCred = new MoviPersConCred();
            $moviPersConCred->cod_persona = $cod_persona;
            $moviPersConCred->cod_credencial = $cod_credencial;
            MoviPersConCred::addAuditoria($moviPersConCred, "RL");
            $moviPersConCred->save();
        }

        Cache::forever("HabiAccesoLastUpdate", Carbon::now()->format('Y-m-d H:i:s'));

        return response(['ok' => 'Se eliminó satisfactoriamente la Tarjeta #' . $cod_credencial], Response::HTTP_OK);
    }

    public function upload()
    {
        $filename = $_FILES['file']['name'];
        $filePath = storage_path('files'); //crear carpeta file
        $destination = $filePath . $filename;
        move_uploaded_file($_FILES['file']['tmp_name'], $destination);
    }
}
