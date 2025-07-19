<?php

namespace App\Http\Controllers;

use App\AptoFisico;
use App\HabiCredPersona;
use App\Imagen;
use App\Persona;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Helpers\ConfigParametro;
use App\Traits\Libgeneral;

use function response;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class Personas extends Controller
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
                return "ab_gestion";
            default:
                return "";
        }
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
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'cod_persona';
        $order = 'asc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        $query = Persona::select();
        //$query = Persona::filtroQuery($query,$filtro);
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {

                    if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }

                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    if ($operacion == "MATCH") {
                        $query->whereRaw("MATCH(nom_persona, ape_persona, nro_documento)AGAINST('$valor' IN BOOLEAN MODE)");
                    } else {
                        $query->where($nombre, $operacion, $valor);
                    }
                }
            }
        }

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }

    public function gridOptions($version = "")
    {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "cod_persona", "name"=> __("Fecha"), "key" => "cod_persona");
                $columnDefs[] = array("prop" => "nom_persona", "name"=> __("Nombre"));
                $columnDefs[] = array("prop" => "ape_persona", "name"=> __("Apellido"));
                $columnDefs[] = array("prop" => "cod_sexo", "name"=> __("Sexo"), "pipe" => "ftSexo");
                $columnDefs[] = array("prop" => "cod_tipo_doc", "name"=> __("Tipo Doc."), "pipe" => "ftDocType");
                $columnDefs[] = array("prop" => "nro_documento", "name"=> __("Nro. Doc."));
                $columnDefs[] = array("prop" => "email", "name"=> __("E-mail"));
                $columnDefs[] = array("prop" => "ind_bloqueo", "name"=> __("Bloqueada"), "pipe" => "ftBoolean");
                $columnDefs[] = array("prop" => "aud_stm_ingreso", "name"=> __("Fecha Alta"), "pipe" => "ftDateTime");
                break;
            default:
                $columnDefs[] = array("field" => "cod_persona", "displayName"=> __("Cód. Persona"));
                $columnDefs[] = array("field" => "nom_persona", "displayName"=> __("Nombre"));
                $columnDefs[] = array("field" => "ape_persona", "displayName"=> __("Apellido"));
                $columnDefs[] = array("field" => "cod_sexo", "displayName"=> __("Sexo"));
                $columnDefs[] = array("field" => "cod_tipo_doc", "displayName"=> __("Tipo Doc."));
                $columnDefs[] = array("field" => "nro_documento", "displayName"=> __("Nro. Doc."));
                $columnDefs[] = array("field" => "email", "displayName"=> __("E-mail"));
                $columnDefs[] = array("field" => "ind_bloqueo", "displayName"=> __("Bloqueada"), "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "aud_stm_ingreso", "displayName"=> __("Fecha Alta"), "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['cod_persona'];

        $filtros[] = array('id' => 'cod_persona', 'name'=> __("Cód. Persona"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'nro_documento', 'name'=> __("Nro. Documento"));

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys" => $columnKeys, "columnDefs" => $columnDefs, "filtros" => $filtros, "rango" => $rango);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function detalle($clave)
    {
        $clave = json_decode(base64_decode($clave), true);
        if (isset($clave[0][0]))
            $cod_persona = $clave[0][0];
        else
            $cod_persona = "";

        $pers = Persona::find($cod_persona);

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

        $persona = array("pers"=>$pers, "datosCred"=>$datosCred);
        return $persona;
    }

    public static function getPersonaxDNI($nro_documento, $cod_ou)
    {
        $datosPersona = array();
        $datosSectores = array();
        $vccod_res = 1; //OK
        $cod_persona = "";

        $queryPer =  Persona::select(
            'maesPersonas.cod_persona',
            'maesPersonas.ape_persona',
            'maesPersonas.nom_persona',
            'maesPersonas.nro_documento',
            'maesPersonas.cod_sexo',
            'maesPersonas.cod_tipo_doc',
            'maesPersonas.email',
            'maesPersonas.ind_bloqueo',
            'maesPersonas.des_motivo_bloqueo',
            'maesPersonas.obs_visitas'
        )
            ->leftjoin('habiCredenciales', 'maesPersonas.cod_persona', '=', 'habiCredenciales.cod_persona')
            ->where('maesPersonas.nro_documento', '=', $nro_documento)
            ->get();
        foreach ($queryPer as $index => $valor) {
            $cod_persona = $valor['cod_persona'];
            $queryPer[$index]['des_persona'] = $valor['ape_persona'] . " " . $valor['nom_persona'];
        }
        if (count($queryPer) < 1 || !$queryPer)
            $vccod_res = 0; //ERROR
        else
            $datosPersona = $queryPer[0];
        if ($cod_persona != "") {
            $datosSectores = DB::select('habiSectoresxCred.cod_sector')
                ->join('habiSectoresxCred', 'habiCredenciales.cod_credencial', '=', 'habiSectoresxCred.cod_credencial')
                ->join('maesSectores', 'maesSectores.cod_sector', '=', 'habiSectoresxCred.cod_sector')
                ->where('habiCredenciales.cod_persona', '=', $cod_persona)
                ->where('habiSectoresxCred.cod_ou', '=', $cod_ou)
                ->get();
        }

        return array("cod_res" => $vccod_res, "datosPersona" => $datosPersona, "datosSectores" => $datosSectores);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_persona' => 'required',
            'ape_persona' => 'required',
            'cod_sexo' => 'required',
            'cod_tipo_doc' => 'required',
            'nro_documento' => 'required',
        ], [
            'nom_persona.required'=> __("Debe ingresar Nombre"),
            'ape_persona.required'=> __("Debe ingresar Apellido"),
            'cod_sexo.required'=> __("Debe seleccionar Sexo"),
            'cod_tipo_doc.required'=> __("Debe ingresar Tipo Documento"),
            'nro_documento.required'=> __("Debe ingresar Nro. Documento")
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }
        $img_apto_fisico = $request->input('img_apto_fisico');
        $fec_otorgamiento_af = $request->input('fec_otorgamiento_af');

        if ($img_apto_fisico && !$fec_otorgamiento_af) {
            return response(['error'=> __("Debe ingresar Fecha Otorgamiento Apto Físico")], Response::HTTP_CONFLICT);
        }

        $cod_persona = Persona::getUuid();

        $persona = new Persona;
        $persona->cod_persona = $cod_persona;
        $persona->nom_persona = $request->input('nom_persona');
        $persona->ape_persona = $request->input('ape_persona');
        $persona->cod_sexo = $request->input('cod_sexo');
        $persona->cod_tipo_doc = $request->input('cod_tipo_doc');
        $persona->nro_documento = $request->input('nro_documento');
        $persona->email = $request->input('email');
        $persona->ind_bloqueo = $request->input('ind_bloqueo');
        $persona->des_motivo_bloqueo = ($persona->ind_bloqueo == 0) ? "" : $request->input('des_motivo_bloqueo');
        //$persona->img_persona = $request->input('img_persona');
        $persona->obs_visitas = $request->input('obs_visitas');
        Persona::addAuditoria($persona, "A");
        $persona->save();

        if ($request->input('img_persona') || $request->input('img_documento')) {
            $imagenes = new Imagen;
            $imagenes->cod_persona = $cod_persona;
            $imagenes->img_persona = $request->input('img_persona');
            $imagenes->img_documento = $request->input('img_documento');
            Imagen::addAuditoria($imagenes, "A");
            $imagenes->save();
        }

        if ($img_apto_fisico) {

            $plazo_vigencia_apto_fisico = ConfigParametro::get('PLAZO_VIGENCIA_APTO_FISICO', false);
            if (!$plazo_vigencia_apto_fisico) {
                $plazo_vigencia_apto_fisico = "1Y";
            }
            $fec_vencimiento_af = Persona::addDateDiff($plazo_vigencia_apto_fisico, $fec_otorgamiento_af);

            $apto = new AptoFisico;
            $apto->cod_persona = $cod_persona;
            $apto->img_apto_fisico = $img_apto_fisico;
            $apto->fec_otorgamiento_af = $fec_otorgamiento_af;
            $apto->fec_vencimiento_af = $fec_vencimiento_af;
            $apto->stm_notificacion = null;
            AptoFisico::addAuditoria($apto, "A");
            $apto->save();
        }

        return response(['ok' => __('La persona fue creada satisfactoriamente con identificador :COD_PERSONA',['COD_PERSONA'=> $persona->cod_persona])], Response::HTTP_OK);
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
        $validator = Validator::make($request->all(), [
            'nom_persona' => 'required',
            'ape_persona' => 'required',
            'cod_sexo' => 'required',
            'cod_tipo_doc' => 'required',
            'nro_documento' => 'required',
        ], [
            'nom_persona.required'=> __("Debe ingresar Nombre"),
            'ape_persona.required'=> __("Debe ingresar Apellido"),
            'cod_sexo.required'=> __("Debe seleccionar Sexo"),
            'cod_tipo_doc.required'=> __("Debe ingresar Tipo Documento"),
            'nro_documento.required'=> __("Debe ingresar Nro. Documento")
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }
        $img_apto_fisico = $request->input('img_apto_fisico');
        $fec_otorgamiento_af = $request->input('fec_otorgamiento_af');

        if ($img_apto_fisico && !$fec_otorgamiento_af) {
            return response(['error'=> __("Debe ingresar Fecha Otorgamiento Apto Físico")], Response::HTTP_CONFLICT);
        }

        $cod_persona = $request->input('cod_persona');

        $ind_bloqueo = $request->input('ind_bloqueo');
        $des_motivo_bloqueo = $request->input('des_motivo_bloqueo');
        if ($ind_bloqueo && $des_motivo_bloqueo == "") {
            return response(['error'=> __("Debe ingresar Motivo Bloqueo")], Response::HTTP_CONFLICT);
        }
        if ($ind_bloqueo && HabiCredPersona::where('cod_persona', $cod_persona)->exists()) {
            return response(['error'=> __("La persona posee habilitaciones, no se puede bloquear")], Response::HTTP_CONFLICT);
        }

        $persona = Persona::find($cod_persona);

        if (!$persona)
            return response(['error'=> __("No se encontró el código de persona")], Response::HTTP_CONFLICT);

        $persona->nom_persona = $request->input('nom_persona');
        $persona->ape_persona = $request->input('ape_persona');
        $persona->cod_sexo = $request->input('cod_sexo');
        $persona->cod_tipo_doc = $request->input('cod_tipo_doc');
        $persona->nro_documento = $request->input('nro_documento');
        $persona->email = $request->input('email');
        $persona->ind_bloqueo = $ind_bloqueo;
        $persona->des_motivo_bloqueo = ($ind_bloqueo == 0) ? "" : $des_motivo_bloqueo;
        //$persona->img_persona = $request->input('img_persona');
        $persona->obs_visitas = $request->input('obs_visitas');
        Persona::addAuditoria($persona, "M");
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

        return response(['ok' => __('Actualización exitosa :COD_PERSONA',['COD_PERSONA'=>$cod_persona])], Response::HTTP_OK);
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
        $cod_persona = $clave[0][0];

        if ($persona = Persona::find($cod_persona)) {
            $persona->delete();
            if ($imagenes = Imagen::find($cod_persona))
                $imagenes->delete();
            if ($apto = AptoFisico::find($cod_persona))
                $apto->delete();
            return response(['ok' => __('Se eliminó satisfactoriamente la persona :COD_PERSONA',['COD_PERSONA'=>$cod_persona])], Response::HTTP_OK);
        }
        return response(['error'=> __("Persona no localizada")], Response::HTTP_CONFLICT);
    }
}
