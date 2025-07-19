<?php

namespace App\Http\Controllers;

set_time_limit(0);

use App\Helpers\ConfigParametro;
use App\Http\Middleware\ComunicacionDispositivos;
use App\PermanenteOK;
use App\Rechazado;
use App\TemporalOK;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\Traits\Libgeneral;
use Illuminate\Support\Facades\Broadcast;
use Carbon\Carbon;


use function response;

class Movimientos extends Controller {

    public static function getAbility($metodo) {
        switch ($metodo) {
            case "indexPermanentes":
            case "indexTemporales":
            case "indexRechazados":
            case "gridOptionsPermanentes":
            case "gridOptionsTemporales":
            case "gridOptionsRechazados":
            case "detallePermanentes":
            case "detalleTemporales":
            case "detalleRechazados":
            case "dashboard":
                return "ab_movimientos";
            default:
                return "";
        }
    }

    /**
     * Movimientos Permitidos Permanentes
     * @param Request $request
     * @param type $export
     * @return typeGrilla 
     */
    public function indexPermanentes(Request $request, $export) {
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'stm_movimiento';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTablaPermanentes($fieldName);

        $query = PermanenteOK::select('moviPermanentesOK.stm_movimiento', 'moviPermanentesOK.cod_credencial', 'moviPermanentesOK.ref_credencial', 
                'moviPermanentesOK.tipo_credencial', 'moviPermanentesOK.cod_persona', 'moviPermanentesOK.nom_persona', 'moviPermanentesOK.ape_persona', 
                'moviPermanentesOK.nro_documento', 'moviPermanentesOK.cod_sector', 'moviPermanentesOK.cod_tema_origen',
                'moviPermanentesOK.cod_sexo', 'moviPermanentesOK.cod_tipo_doc', 'moviPermanentesOK.ind_movimiento', 'maesSectores.nom_sector', 
                'maesTemas.nom_tema', 'moviPermanentesOK.cod_ou_contacto', 'moviPermanentesOK.nom_ou_contacto', 
                'moviPermanentesOK.cod_persona_contacto', 'moviPermanentesOK.nom_persona_contacto', 'moviPermanentesOK.ape_persona_contacto')
                ->leftjoin('maesSectores', 'maesSectores.cod_sector', '=', 'moviPermanentesOK.cod_sector')
                ->leftjoin('maesTemas', 'maesTemas.cod_tema', '=', 'moviPermanentesOK.cod_tema_origen');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($nombre == "ref_credencial") {
                    $operacion = "=";
                    if (is_numeric($valor)) {
                        $valor = (int) $valor;
                        $valor = (string) $valor;
                    }
                } else if ($nombre == "ind_movimiento") {
                    $operacion = "=";
                    $valor = substr($valor, 0, 1);
                }
                else if ($nombre == "des_persona") {
                    $operacion = "MATCH";
                    $valor = LibGeneral::filtroMatch($valor);
                }
                else if ($nombre == "des_persona_contacto") {
                    $operacion = "MATCH_CONTACTO";
                    $valor = LibGeneral::filtroMatch($valor);
                }
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $tabla = self::getTablaPermanentes($nombre);
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(moviPermanentesOK.nom_persona, moviPermanentesOK.ape_persona)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else if($operacion == "MATCH_CONTACTO") {
                        $query->whereRaw("MATCH(moviPermanentesOK.nom_persona_contacto, moviPermanentesOK.ape_persona_contacto)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else {
                        $query->where($tabla . $nombre, $operacion, $valor);    
                    }
                }
            }
        }
        $query->orderBy($tablaOrden . $fieldName, $order);
        if ($export == "false") {
            $resultado = $query->paginate($pageSize);

            return $resultado;
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
                    $writer = WriterFactory::create(Type::XLSX); // for XLSX files
                    break;
            }
            
            $fileName = "Movimientos_permitidos_permanentes.$typeExp";

            $writer = WriterFactory::create($typeExp); // for XLSX files
            $writer->openToBrowser($fileName); // stream data directly to the browser
            $timezoneGMT = new DateTimeZone('GMT');
            $timezoneApp = new DateTimeZone(ConfigParametro::get('TIMEZONE_INFORME', false));

            $query->chunk(1000, function($multipleRows) use ($writer, $timezoneGMT, $timezoneApp) {
                static $FL = true;
                if ($FL) {
                    $writer->addRow(array_keys($multipleRows[0]->getAttributes()));
                    $FL = false;
                }

                $ArExport=$multipleRows->toArray();
                foreach ($ArExport AS &$row) {
                    $fecha = date_create($row['stm_movimiento'], $timezoneGMT)->setTimeZone($timezoneApp);
                    //$row['stm_movimiento'] = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha);
                    $row['stm_movimiento'] = date_format($fecha, "d/m/Y H:i:s");
                }
                $writer->addRows($ArExport);
                unset($ArExport);
            });
            $writer->close();
            return;
        }
    }

    public function indexPermanentesDisplay(Request $request, $export) {
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'stm_movimiento';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTablaPermanentes($fieldName);

        $query = PermanenteOK::select('moviPermanentesOK.stm_movimiento', 
                'moviPermanentesOK.cod_credencial', 
//                'moviPermanentesOK.ref_credencial', 'moviPermanentesOK.tipo_credencial', 'moviPermanentesOK.cod_persona', 
                'moviPermanentesOK.nom_persona', 'moviPermanentesOK.ape_persona', 
//                'moviPermanentesOK.nro_documento', 'moviPermanentesOK.cod_sector', 'moviPermanentesOK.cod_tema_origen',
//                'moviPermanentesOK.cod_sexo', 'moviPermanentesOK.cod_tipo_doc', 
                'moviPermanentesOK.ind_movimiento', 
//                'maesSectores.nom_sector', 
//                'maesTemas.nom_tema', 
                'moviPermanentesOK.nom_sector', 
                'moviPermanentesOK.nom_tema' 
//                'moviPermanentesOK.cod_ou_contacto', 'moviPermanentesOK.nom_ou_contacto', 
//                'moviPermanentesOK.cod_persona_contacto', 'moviPermanentesOK.nom_persona_contacto', 'moviPermanentesOK.ape_persona_contacto'
                )
//                ->leftjoin('maesSectores', 'maesSectores.cod_sector', '=', 'moviPermanentesOK.cod_sector')
//                ->leftjoin('maesTemas', 'maesTemas.cod_tema', '=', 'moviPermanentesOK.cod_tema_origen')
                
                ;
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($nombre == "ref_credencial") {
                    $operacion = "=";
                    if (is_numeric($valor)) {
                        $valor = (int) $valor;
                        $valor = (string) $valor;
                    }
                } else if ($nombre == "ind_movimiento") {
                    $operacion = "=";
                    $valor = substr($valor, 0, 1);
                }
                else if ($nombre == "des_persona") {
                    $operacion = "MATCH";
                    $valor = LibGeneral::filtroMatch($valor);
                }
                else if ($nombre == "des_persona_contacto") {
                    $operacion = "MATCH_CONTACTO";
                    $valor = LibGeneral::filtroMatch($valor);
                }
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $tabla = self::getTablaPermanentes($nombre);
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(moviPermanentesOK.nom_persona, moviPermanentesOK.ape_persona)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else if($operacion == "MATCH_CONTACTO") {
                        $query->whereRaw("MATCH(moviPermanentesOK.nom_persona_contacto, moviPermanentesOK.ape_persona_contacto)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else {
                        $query->where($tabla . $nombre, $operacion, $valor);    
                    }
                }
            }
        }
        $query->orderBy($tablaOrden . $fieldName, $order);

        if ($export == "false") {
            $resultado = $query->paginate($pageSize);
            return $resultado;
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
                    $writer = WriterFactory::create(Type::XLSX); // for XLSX files
                    break;
            }
            
            $fileName = "Movimientos_permitidos_permanentes.$typeExp";

            $writer = WriterFactory::create($typeExp); // for XLSX files
            $writer->openToBrowser($fileName); // stream data directly to the browser
            $timezoneGMT = new DateTimeZone('GMT');
            $timezoneApp = new DateTimeZone(ConfigParametro::get('TIMEZONE_INFORME', false));

            $query->chunk(1000, function($multipleRows) use ($writer, $timezoneGMT, $timezoneApp) {
                static $FL = true;
                if ($FL) {
                    $writer->addRow(array_keys($multipleRows[0]->getAttributes()));
                    $FL = false;
                }

                $ArExport=$multipleRows->toArray();

                foreach ($ArExport AS &$row) {
                    $fecha = date_create($row['stm_movimiento'], $timezoneGMT)->setTimeZone($timezoneApp);
                    //$row['stm_movimiento'] = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha);
                    $row['stm_movimiento'] = date_format($fecha, "d/m/Y H:i:s");
                }
                $writer->addRows($ArExport);
                unset($ArExport);
            });
            $writer->close();
            return;
        }
    }

    /**
     * Movimientos Permitidos Temporales
     * @param Request $request
     * @param type $export
     * @return typeGrilla 
     */
    public function indexTemporales(Request $request, $export) {
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'stm_movimiento';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTablaTemporales($fieldName);

        $query = TemporalOK::select('moviTemporalesOK.stm_movimiento', 'moviTemporalesOK.cod_credencial', 'moviTemporalesOK.ref_credencial', 
                'moviTemporalesOK.tipo_credencial', 'moviTemporalesOK.cod_persona', 'moviTemporalesOK.nom_persona', 'moviTemporalesOK.ape_persona', 
                'moviTemporalesOK.nro_documento', 'moviTemporalesOK.cod_sector', 'moviTemporalesOK.cod_tema_origen', 
                'moviTemporalesOK.cod_sexo', 'moviTemporalesOK.cod_tipo_doc', 'moviTemporalesOK.ind_movimiento', 'maesSectores.nom_sector', 
                'maesTemas.nom_tema', 'moviTemporalesOK.cod_ou_contacto', 'moviTemporalesOK.nom_ou_contacto', 
                'moviTemporalesOK.cod_persona_contacto', 'moviTemporalesOK.nom_persona_contacto', 'moviTemporalesOK.ape_persona_contacto')
                ->leftjoin('maesSectores', 'maesSectores.cod_sector', '=', 'moviTemporalesOK.cod_sector')
                ->leftjoin('maesTemas', 'maesTemas.cod_tema', '=', 'moviTemporalesOK.cod_tema_origen');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($nombre == "ref_credencial") {
                    $operacion = "=";
                    if (is_numeric($valor)) {
                        $valor = (int) $valor;
                        $valor = (string) $valor;
                    }
                } else if ($nombre == "ind_movimiento") {
                    $operacion = "=";
                    $valor = substr($valor, 0, 1);
                }
                else if ($nombre == "des_persona") {
                    $operacion = "MATCH";
                    $valor = LibGeneral::filtroMatch($valor);
                }                
                else if ($nombre == "des_persona_contacto") {
                    $operacion = "MATCH_CONTACTO";
                    $valor = LibGeneral::filtroMatch($valor);
                }
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $tabla = self::getTablaTemporales($nombre);
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(moviTemporalesOK.nom_persona, moviTemporalesOK.ape_persona)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else if($operacion == "MATCH_CONTACTO") {
                        $query->whereRaw("MATCH(moviTemporalesOK.nom_persona_contacto, moviTemporalesOK.ape_persona_contacto)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else {
                        $query->where($tabla . $nombre, $operacion, $valor);    
                    }
                }
            }
        }
        $query->orderBy($tablaOrden . $fieldName, $order);

        if ($export == "false") {
            $resultado = $query->paginate($pageSize);
            return $resultado;
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
            $fileName = "Movimientos_permitidos_temporales.$typeExp";
            $writer = WriterFactory::create($typeExp); // for XLSX files
            $writer->openToBrowser($fileName); // stream data directly to the browser
            $timezoneGMT = new DateTimeZone('GMT');
            $timezoneApp = new DateTimeZone(ConfigParametro::get('TIMEZONE_INFORME', false));

            $query->chunk(1000, function($multipleRows) use ($writer, $timezoneGMT, $timezoneApp) {
                static $FL = true;
                if ($FL) {
                    $writer->addRow(array_keys($multipleRows[0]->getAttributes()));
                    $FL = false;
                }
                $ArExport=$multipleRows->toArray();
                foreach ($ArExport AS &$row) {
                    $fecha = date_create($row['stm_movimiento'], $timezoneGMT)->setTimeZone($timezoneApp);
                    $row['stm_movimiento'] = date_format($fecha, "d/m/Y H:i:s");
                }
                $writer->addRows($ArExport);
                unset($ArExport);
            });
            $writer->close();
            return;
        }
    }

    private static function getTablaPermanentes($campo) {
        $tabla = "";
        switch ($campo) {
            case "nom_sector":
                $tabla = "maesSectores.";
                break;
            case "nom_tema":
                $tabla = "maesTemas.";
                break;
            default:
                $tabla = "moviPermanentesOK.";
                break;
        }
        return $tabla;
    }
    
    private static function getTablaTemporales($campo) {
        $tabla = "";
        switch ($campo) {
            case "nom_sector":
                $tabla = "maesSectores.";
                break;
            case "nom_tema":
                $tabla = "maesTemas.";
                break;
            default:
                $tabla = "moviTemporalesOK.";
                break;
        }
        return $tabla;
    }

    private static function getTablaRechazados($campo) {
        $tabla = "";
        switch ($campo) {
            case "nom_sector":
                $tabla = "maesSectores.";
                break;
            case "nom_tema":
                $tabla = "maesTemas.";
                break;
                break;
            default:
                $tabla = "moviRechazados.";
                break;
        }
        return $tabla;
    }

    public function detallePermanentes($clave, $cod_ou) {
        $clave = json_decode(base64_decode($clave), true);
        $stm_movimiento = $clave[0][0];
        $datosPersona = array();

        $vccod_res = 1;

        $query = PermanenteOK::select('moviPermanentesOK.stm_movimiento','moviPermanentesOK.cod_credencial', 'moviPermanentesOK.ref_credencial', 'moviPermanentesOK.tipo_credencial', 
                'moviPermanentesOK.cod_persona', 'moviPermanentesOK.nom_persona', 'moviPermanentesOK.ape_persona', 'moviPermanentesOK.nro_documento', 
                'moviPermanentesOK.cod_sector', 'moviPermanentesOK.cod_tema_origen', 'moviPermanentesOK.cod_sexo', 'moviPermanentesOK.cod_tipo_doc', 'maesPersonas.email', 
                'moviPermanentesOK.cod_ou_contacto', 'moviPermanentesOK.nom_ou_contacto', 'moviPermanentesOK.cod_persona_contacto', 'moviPermanentesOK.nom_persona_contacto', 'moviPermanentesOK.ape_persona_contacto')
                ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'moviPermanentesOK.cod_persona')
                ->where('moviPermanentesOK.stm_movimiento', '=', $stm_movimiento)
                ->first();

        if (!$query) {
            $vccod_res = 0;
            return response(array("cod_res" => 0, "datosPersona" => array(), "clave"=>$clave), Response::HTTP_NOT_FOUND);
        } 

        $datosPersona = $query->toArray();
        $datosPersona['tipo_habilitacion'] = "P";
        $resp = $datosPersona;
        $resp["cod_res"] = $vccod_res;  //Solo para la version angular1
        $resp["datosPersona"] = $datosPersona;  //Solo para la version angular1
        $resp["clave"]=$clave;  //Solo para la version angular1
        
        return response($resp, Response::HTTP_OK);

    }
    
    public function detalleTemporales($clave, $cod_ou) {
        $clave = json_decode(base64_decode($clave), true); 
        $stm_movimiento = $clave[0][0];
        $vccod_res = 1;
        $query = TemporalOK::select('moviTemporalesOK.cod_credencial', 'moviTemporalesOK.ref_credencial', 'moviTemporalesOK.tipo_credencial', 
                'moviTemporalesOK.cod_persona', 'moviTemporalesOK.nom_persona', 'moviTemporalesOK.ape_persona', 'moviTemporalesOK.nro_documento', 
                'moviTemporalesOK.cod_sector', 'moviTemporalesOK.cod_tema_origen', 'moviTemporalesOK.cod_sexo', 
                'moviTemporalesOK.cod_tipo_doc', 'maesPersonas.email', 'moviTemporalesOK.cod_ou_contacto', 'moviTemporalesOK.nom_ou_contacto', 
                'moviTemporalesOK.cod_persona_contacto', 'moviTemporalesOK.nom_persona_contacto', 'moviTemporalesOK.ape_persona_contacto')
                ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'moviTemporalesOK.cod_persona')
                ->where('moviTemporalesOK.stm_movimiento', '=', $stm_movimiento)
                ->get();
        if (empty($query[0])) {
            $vccod_res = 0;
        }
        
        $datosPersona = $query[0];
        $datosPersona['tipo_habilitacion'] = "T";

        return array("cod_res" => $vccod_res, "datosPersona" =>$datosPersona, "clave"=>$clave);
    }

    public function detalleRechazados($clave, $cod_ou) {
        $clave = json_decode(base64_decode($clave), true); 
        $stm_movimiento = $clave[0][0];
        $vccod_res = 1;
        $query = Rechazado::select('moviRechazados.cod_credencial', 'moviRechazados.ref_credencial', 'moviRechazados.tipo_credencial', 
                'moviRechazados.tipo_habilitacion', 'moviRechazados.cod_persona', 'moviRechazados.nom_persona', 'moviRechazados.ape_persona', 
                'moviRechazados.nro_documento', 'moviRechazados.cod_sector', 'moviRechazados.cod_tema_origen', 'moviRechazados.cod_sexo', 
                'moviRechazados.cod_tipo_doc', 'moviRechazados.ind_rechazo', 'maesPersonas.email')
                ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'moviRechazados.cod_persona')
                ->where('moviRechazados.stm_movimiento', '=', $stm_movimiento)
                ->get();
        if (empty($query[0])) {
            $vccod_res = 0;
        }
        return array("cod_res" => $vccod_res, "datosPersona" => $query[0], "clave"=>$clave);
    }

    public function dashboard($cant_filas) {
        if ($cant_filas=="undefined") $cant_filas="";
        $limit = ($cant_filas != "") ? $cant_filas : 7;
		$limit = $limit*3;
        $temp = array();
        $dashboard_labels = array();
        $dashboard_data = array();
        $permanentes = array();
        $temporales = array();
        $rechazados = array();
		
		$result= Cache::get('dashboard_data');
		if ($result)
			return array("dashboard_data" => $result);
		
        $query = DB::select(DB::raw("SELECT DATE_FORMAT(DATE_ADD(stm_movimiento, INTERVAL -3 HOUR), '%Y-%m-%d') as stm_movimiento,count(*) as cantidad, 'permanentes' as 'tabla' 
                                    FROM moviPermanentesOK
                                    GROUP BY DATE_FORMAT(DATE_ADD(stm_movimiento, INTERVAL -3 HOUR), '%Y-%m-%d')
                                    UNION
                                    SELECT DATE_FORMAT(DATE_ADD(stm_movimiento, INTERVAL -3 HOUR), '%Y-%m-%d') as stm_movimiento,count(*) as cantidad, 'temporales' as 'tabla' 
                                    FROM moviTemporalesOK
                                    GROUP BY DATE_FORMAT(DATE_ADD(stm_movimiento, INTERVAL -3 HOUR), '%Y-%m-%d')
                                    UNION
                                    SELECT DATE_FORMAT(DATE_ADD(stm_movimiento, INTERVAL -3 HOUR), '%Y-%m-%d') as stm_movimiento,count(*) as cantidad,'rechazados' as 'tabla' 
                                    FROM moviRechazados
                                    GROUP BY DATE_FORMAT(DATE_ADD(stm_movimiento, INTERVAL -3 HOUR), '%Y-%m-%d')
                                    ORDER BY stm_movimiento DESC LIMIT $limit"));
        foreach ($query as $row) {
            if (!isset($temp[$row->stm_movimiento]))
                $temp[$row->stm_movimiento] = array(0, 0, 0);

            if ($row->tabla == "permanentes") {
                $temp[$row->stm_movimiento][0] = $row->cantidad;
            } 
            else if ($row->tabla == "temporales") {
                $temp[$row->stm_movimiento][1] = $row->cantidad;
            }
            else if ($row->tabla == "rechazados") {
                $temp[$row->stm_movimiento][2] = $row->cantidad;
            }
        }
        foreach ($temp as $key => $permrech) {
            $permanentes[] = array("x" => $key, "y" => $permrech[0]);
            $temporales[] = array("x" => $key, "y" => $permrech[1]);
            $rechazados[] = array("x" => $key, "y" => $permrech[2]);
        }

        $dashboard_data[0] = $permanentes;
        $dashboard_data[1] = $temporales;
        $dashboard_data[2] = $rechazados;
		Cache::put('dashboard_data', $dashboard_data, 60);

        return array("dashboard_data" => $dashboard_data);
    }

    public function indexRechazados(Request $request, $export) {
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'stm_movimiento';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTablaRechazados($fieldName);
//DB::enableQueryLog();
        $query = Rechazado::select('moviRechazados.stm_movimiento', 'moviRechazados.cod_credencial', 'moviRechazados.ref_credencial', 
                'moviRechazados.tipo_credencial', 'moviRechazados.tipo_habilitacion', 'moviRechazados.cod_persona', 'moviRechazados.nom_persona', 
                'moviRechazados.ape_persona', 'moviRechazados.nro_documento', 'moviRechazados.cod_sector', 'moviRechazados.cod_tema_origen', 
                'moviRechazados.cod_sexo', 'moviRechazados.cod_tipo_doc', 'moviRechazados.ind_movimiento', 'maesSectores.nom_sector', 
                'maesTemas.nom_tema', 'moviRechazados.ind_rechazo')
                ->join('maesSectores', 'maesSectores.cod_sector', '=', 'moviRechazados.cod_sector')
                ->join('maesTemas', 'maesTemas.cod_tema', '=', 'moviRechazados.cod_tema_origen');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($nombre == "ref_credencial") {
                        $operacion = "=";
                        if (is_numeric($valor)) {
                            $valor = (int) $valor;
                            $valor = (string) $valor;
                        }
                    } else if ($nombre == "tipo_habilitacion" || $nombre == "ind_movimiento") {
                        $operacion = "=";
                        $valor = substr($valor, 0, 1);
                    }
                    else if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }
                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $tabla = self::getTablaRechazados($nombre);
                    
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(moviRechazados.nom_persona, moviRechazados.ape_persona)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else {
                        $query->where($tabla . $nombre, $operacion, $valor);    
                    }
                }
            }
        }
        $query->orderBy($tablaOrden . $fieldName, $order);

        if ($export == "false") {
            $resultado = $query->paginate($pageSize);
            return $resultado;
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
            $fileName = "Movimientos_rechazados.$typeExp";
            $writer = WriterFactory::create($typeExp); // for XLSX files
            $writer->openToBrowser($fileName); // stream data directly to the browser
            $timezoneGMT = new DateTimeZone('GMT');
            $timezoneApp = new DateTimeZone(ConfigParametro::get('TIMEZONE_INFORME', false));
            $query->chunk(1000, function($multipleRows) use ($writer, $timezoneGMT, $timezoneApp) {
                static $FL = true;
                if ($FL) {
                    $writer->addRow(array_keys($multipleRows[0]->getAttributes()));
                    $FL = false;
                }
                $ArExport = $multipleRows->toArray();
                foreach ($ArExport AS &$row) {
                    $fecha = date_create($row['stm_movimiento'], $timezoneGMT)->setTimeZone($timezoneApp);
                    //$row['stm_movimiento'] = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha);
                    $row['stm_movimiento'] = date_format($fecha, "d/m/Y H:i:s");
                }
                $writer->addRows($ArExport);
                unset($ArExport);
            });
            $writer->close();
            return;
        }
    }

    public function gridOptionsPermanentes($version = "") {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "stm_movimiento", "name"=> __("Fecha"), "key" => "stm_movimiento", "pipe"=>"ftDateTime");
                $columnDefs[] = array("prop" => "cod_credencial", "name"=> __("Cód Tarjeta"), "pipe"=>"ftCredencial" );
                $columnDefs[] = array("prop" => "ref_credencial", "name"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("prop" => "nom_persona", "name"=> __("Nombre"));
                $columnDefs[] = array("prop" => "ape_persona", "name"=> __("Apellido"));
                $columnDefs[] = array("prop" => "nro_documento", "name"=> __("Nro. Doc."));
                $columnDefs[] = array("prop" => "ind_movimiento", "name"=> __("Movimiento"), "pipe"=>"ftIndMovimiento");
                $columnDefs[] = array("prop" => "nom_tema", "name"=> __("Lector"));
                $columnDefs[] = array("prop" => "cod_sector", "name"=> __("Sector") , "pipe"=>"ftSectores");
                $columnDefs[] = array("prop" => "nom_ou_contacto", "name"=> __("Organización Contacto"));
                $columnDefs[] = array("prop" => "nom_persona_contacto", "name"=> __("Nombre Persona Contacto"));
                $columnDefs[] = array("prop" => "ape_persona_contacto", "name"=> __("Apellido Persona Contacto"));
                break;
            default:
                $columnDefs[] = array("field" => "stm_movimiento", "displayName"=> __("Fecha"), "width" => 170, "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "cod_credencial", "displayName"=> __("Cód. Tarjeta"), "cellFilter" => "ftTarjeta");
                $columnDefs[] = array("field" => "ref_credencial", "displayName"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("field" => "nom_persona", "displayName"=> __("Nombre"));
                $columnDefs[] = array("field" => "ape_persona", "displayName"=> __("Apellido"));
                $columnDefs[] = array("field" => "nro_documento", "displayName"=> __("Nro. Doc."));
                $columnDefs[] = array("field" => "ind_movimiento", "displayName"=> __("Movimiento"), "cellFilter" => "ftMovimiento");
                $columnDefs[] = array("field" => "nom_tema", "displayName"=> __("Lector"));
                $columnDefs[] = array("field" => "nom_sector", "displayName"=> __("Sector"));
                $columnDefs[] = array("field" => "nom_ou_contacto", "displayName"=> __("Organización Contacto"));
                $columnDefs[] = array("field" => "nom_persona_contacto", "displayName"=> __("Nombre Persona Contacto"));
                $columnDefs[] = array("field" => "ape_persona_contacto", "displayName"=> __("Apellido Persona Contacto"));
        }
        $columnKeys = ['stm_movimiento'];
        
        $filtros[] = array('id' => 'cod_credencial', 'name'=> __("Cód. Tarjeta"));
        $filtros[] = array('id' => 'ref_credencial', 'name'=> __("Ref. Tarjeta"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'nro_documento', 'name'=> __("Nro. Documento"));
        $filtros[] = array('id' => 'nom_tema', 'name'=> __("Lector"));
        $filtros[] = array('id' => 'nom_sector', 'name'=> __("Sector"));
        $filtros[] = array('id' => 'nom_ou_contacto', 'name'=> __("Organización Contacto"));
        $filtros[] = array('id' => 'des_persona_contacto', 'name'=> __("Apellido y Nombre Contacto"));

        $rango['desde'] = array('id' => 'stm_movimiento', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public function gridOptionsPermanentesDisplay($version = "") {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "stm_movimiento", "name"=> __("Fecha"), "key" => "stm_movimiento", "pipe"=>"ftDateTime");
//                $columnDefs[] = array("prop" => "cod_credencial", "name"=> __("Cód Tarjeta"), "pipe"=>"ftCredencial");
//                $columnDefs[] = array("prop" => "ref_credencial", "name"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("prop" => "nom_persona", "name"=> __("Nombre"));
                $columnDefs[] = array("prop" => "ape_persona", "name"=> __("Apellido"));
                $columnDefs[] = array("prop" => "nro_documento", "name"=> __("Nro. Doc."));
                $columnDefs[] = array("prop" => "ind_movimiento", "name"=> __("Movimiento"), "pipe"=>"ftIndMovimiento");
                $columnDefs[] = array("prop" => "nom_tema", "name"=> __("Lector"));
                $columnDefs[] = array("prop" => "cod_sector", "name"=> __("Sector"), "pipe"=>"ftSectores");
//                $columnDefs[] = array("prop" => "nom_ou_contacto", "name"=> __("Organización Contacto"));
//                $columnDefs[] = array("prop" => "nom_persona_contacto", "name"=> __("Nombre Persona Contacto"));
//                $columnDefs[] = array("prop" => "ape_persona_contacto", "name"=> __("Apellido Persona Contacto"));
                break;
            default:
                $columnDefs[] = array("field" => "stm_movimiento", "displayName"=> __("Fecha"), "width" => 170, "type" => "date", "cellFilter" => "ftDateTime");
//                $columnDefs[] = array("field" => "cod_credencial", "displayName"=> __("Cód. Tarjeta"), "cellFilter" => "ftTarjeta");
//                $columnDefs[] = array("field" => "ref_credencial", "displayName"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("field" => "nom_persona", "displayName"=> __("Nombre"));
                $columnDefs[] = array("field" => "ape_persona", "displayName"=> __("Apellido"));
                $columnDefs[] = array("field" => "nro_documento", "displayName"=> __("Nro. Doc."));
                $columnDefs[] = array("field" => "ind_movimiento", "displayName"=> __("Movimiento"), "cellFilter" => "ftMovimiento");
                $columnDefs[] = array("field" => "nom_tema", "displayName"=> __("Lector"));
                $columnDefs[] = array("field" => "nom_sector", "displayName"=> __("Sector"));
//                $columnDefs[] = array("field" => "nom_ou_contacto", "displayName"=> __("Organización Contacto"));
//                $columnDefs[] = array("field" => "nom_persona_contacto", "displayName"=> __("Nombre Persona Contacto"));
//                $columnDefs[] = array("field" => "ape_persona_contacto", "displayName"=> __("Apellido Persona Contacto"));
        }
        $columnKeys = ['stm_movimiento'];
        
        $filtros[] = array('id' => 'cod_credencial', 'name'=> __("Cód. Tarjeta"));
//        $filtros[] = array('id' => 'ref_credencial', 'name'=> __("Ref. Tarjeta"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'nro_documento', 'name'=> __("Nro. Documento"));
        $filtros[] = array('id' => 'nom_tema', 'name'=> __("Lector"));
        $filtros[] = array('id' => 'nom_sector', 'name'=> __("Sector"));
//        $filtros[] = array('id' => 'nom_ou_contacto', 'name'=> __("Organización Contacto"));
//        $filtros[] = array('id' => 'des_persona_contacto', 'name'=> __("Apellido y Nombre Contacto"));

        $rango['desde'] = array('id' => 'stm_movimiento', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }
    


    public function gridOptionsTemporales($version = "") {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "stm_movimiento", "name"=> __("Fecha"), "key" => "stm_movimiento", "pipe"=>"ftDateTime");
                $columnDefs[] = array("prop" => "cod_credencial", "name"=> __("Cód. Tarjeta"), "pipe"=>"ftCredencial");
                $columnDefs[] = array("prop" => "ref_credencial", "name"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("prop" => "nom_persona", "name"=> __("Nombre"));
                $columnDefs[] = array("prop" => "ape_persona", "name"=> __("Apellido"));
                $columnDefs[] = array("prop" => "nro_documento", "name"=> __("Nro. Doc."));
                $columnDefs[] = array("prop" => "ind_movimiento", "name"=> __("Movimiento"), "pipe"=>"ftIndMovimiento");
                $columnDefs[] = array("prop" => "nom_tema", "name"=> __("Lector"));
                $columnDefs[] = array("prop" => "cod_sector", "name"=> __("Sector"), "pipe"=>"ftSectores");
                $columnDefs[] = array("prop" => "nom_ou_contacto", "name"=> __("Organización Contacto"));
                $columnDefs[] = array("prop" => "nom_persona_contacto", "name"=> __("Nombre Persona Contacto"));
                $columnDefs[] = array("prop" => "ape_persona_contacto", "name"=> __("Apellido Persona Contacto"));
                break;
            default:
                $columnDefs[] = array("field" => "stm_movimiento", "displayName"=> __("Fecha"), "width" => 170, "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "cod_credencial", "displayName"=> __("Cód. Tarjeta"), "cellFilter" => "ftTarjeta");
                $columnDefs[] = array("field" => "ref_credencial", "displayName"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("field" => "nom_persona", "displayName"=> __("Nombre"));
                $columnDefs[] = array("field" => "ape_persona", "displayName"=> __("Apellido"));
                $columnDefs[] = array("field" => "nro_documento", "displayName"=> __("Nro. Doc."));
                $columnDefs[] = array("field" => "ind_movimiento", "displayName"=> __("Movimiento"), "cellFilter" => "ftMovimiento");
                $columnDefs[] = array("field" => "nom_tema", "displayName"=> __("Lector"));
                $columnDefs[] = array("field" => "nom_sector", "displayName"=> __("Sector"));
                $columnDefs[] = array("field" => "nom_ou_contacto", "displayName"=> __("Organización Contacto"));
                $columnDefs[] = array("field" => "nom_persona_contacto", "displayName"=> __("Nombre Persona Contacto"));
                $columnDefs[] = array("field" => "ape_persona_contacto", "displayName"=> __("Apellido Persona Contacto"));
        }
        $columnKeys = ['stm_movimiento'];
        
        $filtros[] = array('id' => 'cod_credencial', 'name'=> __("Cód. Tarjeta"));
        $filtros[] = array('id' => 'ref_credencial', 'name'=> __("Ref. Tarjeta"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'nro_documento', 'name'=> __("Nro. Documento"));
        $filtros[] = array('id' => 'nom_tema', 'name'=> __("Lector"));
        $filtros[] = array('id' => 'nom_sector', 'name'=> __("Sector"));
        $filtros[] = array('id' => 'nom_ou_contacto', 'name'=> __("Organización Contacto"));
        $filtros[] = array('id' => 'des_persona_contacto', 'name'=> __("Apellido y Nombre Contacto"));

        $rango['desde'] = array('id' => 'stm_movimiento', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public function gridOptionsRechazados($version = "") {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "stm_movimiento", "name"=> __("Fecha"), "key" => "stm_movimiento", "pipe"=>"ftDateTime");
                $columnDefs[] = array("prop" => "cod_credencial", "name"=> __("Cód. Tarjeta"), "pipe"=>"ftCredencial");
                $columnDefs[] = array("prop" => "ref_credencial", "name"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("prop" => "nom_persona", "name"=> __("Nombre"));
                $columnDefs[] = array("prop" => "ape_persona", "name"=> __("Apellido"));
                $columnDefs[] = array("prop" => "nro_documento", "name"=> __("Nro. Doc."));
                $columnDefs[] = array("prop" => "ind_movimiento", "name"=> __("Movimiento"), "pipe"=>"ftIndMovimiento");
                $columnDefs[] = array("prop" => "nom_tema", "name"=> __("Lector"));
                $columnDefs[] = array("prop" => "cod_sector", "name"=> __("Sector"), "pipe"=>"ftSectores");
                $columnDefs[] = array("prop" => "tipo_habilitacion", "name"=> __("Tipo Hab."));
                $columnDefs[] = array("prop" => "ind_rechazo", "name"=> __("Ind Rechazo"));
                break;
            default:
                $columnDefs[] = array("field" => "stm_movimiento", "displayName"=> __("Fecha"), "width" => 170, "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "cod_credencial", "displayName"=> __("Cód. Tarjeta"), "cellFilter" => "ftTarjeta");
                $columnDefs[] = array("field" => "ref_credencial", "displayName"=> __("Ref. Tarjeta"));
                $columnDefs[] = array("field" => "nom_persona", "displayName"=> __("Nombre"));
                $columnDefs[] = array("field" => "ape_persona", "displayName"=> __("Apellido"));
                $columnDefs[] = array("field" => "nro_documento", "displayName"=> __("Nro. Doc."));
                $columnDefs[] = array("field" => "ind_movimiento", "displayName"=> __("Movimiento"), "cellFilter" => "ftMovimiento");
                $columnDefs[] = array("field" => "nom_tema", "displayName"=> __("Lector"));
                $columnDefs[] = array("field" => "nom_sector", "displayName"=> __("Sector"));
                $columnDefs[] = array("field" => "tipo_habilitacion", "displayName"=> __("Tipo Hab."), "cellFilter" => "ftTipoHab");
                $columnDefs[] = array("field" => "ind_rechazo", "displayName"=> __("Ind Rechazo"), "cellFilter" => "ftTipoRechazo");
        }
        $columnKeys = ['stm_movimiento'];
        
        $filtros[] = array('id' => 'cod_credencial', 'name'=> __("Cód. Tarjeta"));
        $filtros[] = array('id' => 'ref_credencial', 'name'=> __("Ref. Tarjeta"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'nro_documento', 'name'=> __("Nro. Documento"));
        $filtros[] = array('id' => 'nom_tema', 'name'=> __("Lector"));
        $filtros[] = array('id' => 'nom_sector', 'name'=> __("Sector"));

        $rango['desde'] = array('id' => 'stm_movimiento', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public function test(Request $request) {
        $cod_credencial = preg_replace("/[^0-9]/", "", $request->input('cod_credencial'));
        $cod_tema = $request->input('cod_tema_origen');
        $ind_separa_facility_code = $request->input('ind_separa_facility_code');
        $bits_wiegand = ConfigParametro::get('BITS_WIEGAND', false, 26);
        if ($cod_credencial == "" || $cod_tema == "")
            return;
        if ($ind_separa_facility_code == "1") {

            switch ($bits_wiegand) {
                case 35:
                    $gen = substr($cod_credencial,0,strlen($cod_credencial)-7);
                    $par = substr($cod_credencial, -7);

                    if (($gen >> 12) !=0) 
                        return response(["error"=>"Facility code supera el máximo"], Response::HTTP_CONFLICT);
                    if (($par >> 20) !=0) 
                        return response(["error"=>"User code supera el máximo"], Response::HTTP_CONFLICT);

                    $cod_credencial = bcadd(bcmul($gen , 1048576,0) + $par,0) ;
                    break;
                    
                default:
                    $gen = substr($cod_credencial,0,strlen($cod_credencial)-5);
                    $par = substr($cod_credencial, -5);

                    if (($gen >> 8) !=0) 
                        return response(["error"=>"Facility code supera el máximo"], Response::HTTP_CONFLICT);
                    if (($par >> 16) !=0) 
                        return response(["error"=>"User code supera el máximo"], Response::HTTP_CONFLICT);

                    $cod_credencial = ($gen << 16) + $par;
                    break;
            }
        }

        $fake_credencial = array('cod_tema' => $cod_tema, 'valor' => $cod_credencial);
        $cd = new ComunicacionDispositivos();
        $ret = $cd->leeCredencial($fake_credencial);
        if ($ret->status() == 200) {
            //$respuesta = $ret->original['rs485'];
            if (isset($ret->original['channel']));
                Broadcast::driver('fast-web-socket')->broadcast([$ret->original['channel']], $ret->original['event'],  $ret->original['context']);
            // $this->printDebugInfo($sendtxt);
        }

        return response("Listo",$ret->status());
    }

    protected static function sendMovimientosSync($url, $movimiento) {
        $client = new Client(['verify' => false]);
        try {
            $res = $client->request('POST', $url, ['json' => $movimiento ]);
            $vvrespuesta = array($res->getStatusCode(), $res->getBody()->getContents());
        } catch(\Exception $e) {
            $vvrespuesta = array($e->getCode(), $e->getMessage());
        }
        if (strpos($vvrespuesta[1],"1062 Duplicate entry")!==false){
            $vvrespuesta = array("200", $movimiento->stm_movimiento);
        }

        return $vvrespuesta;
    }

    public static function syncPermanentes($lastUpdate, $urlMaster) {

        $url = $urlMaster .'/movimientos/sync/permanentes';
        $vaerrors = array();
        $stm_movimiento = '';
        $ind_grabacion = false;      

        DB::table('moviPermanentesOK')->where('stm_movimiento', '>', $lastUpdate)
            ->orderBy('stm_movimiento', 'asc')
            ->chunk(100, function($movimientos) use ($url, &$stm_movimiento, &$vaerrors) {
                foreach($movimientos as $movimiento) {
                    $vvrespuesta = self::sendMovimientosSync($url, $movimiento);
                    if ($vvrespuesta[0] == Response::HTTP_OK) {
                        $stm_movimiento = $vvrespuesta[1];
                    } else {
                        $vaerrors[] = $vvrespuesta;
                    }
                }
            });
        
        if ($stm_movimiento !== '') {
            $ind_grabacion = true;
            $lastUpdate = $stm_movimiento;
        }

        return array('ind_grabacion' => $ind_grabacion, 'lastUpdate' => $lastUpdate, 'errors' => $vaerrors);
    }

    public static function syncTemporales($lastUpdate, $urlMaster) {

        $url = $urlMaster .'/movimientos/sync/temporales';
        $vaerrors = array();
        $stm_movimiento = '';
        $ind_grabacion = false;

        DB::table('moviTemporalesOK')->where('stm_movimiento', '>', $lastUpdate)
            ->orderBy('stm_movimiento', 'asc')
            ->chunk(100, function($movimientos) use ($url, &$stm_movimiento, &$vaerrors) {
                foreach($movimientos as $movimiento) {
                    $vvrespuesta = self::sendMovimientosSync($url, $movimiento);
                    if ($vvrespuesta[0] == Response::HTTP_OK) {
                        $stm_movimiento = $vvrespuesta[1];
                    } else {
                        $vaerrors[] = $vvrespuesta;
                    }
                }
            });
        
        if ($stm_movimiento !== '') {
            $ind_grabacion = true;
            $lastUpdate = $stm_movimiento;
        }

        return array('ind_grabacion' => $ind_grabacion, 'lastUpdate' => $lastUpdate, 'errors' => $vaerrors);
    }

    public static function syncRechazados($lastUpdate, $urlMaster) {

        $url = $urlMaster .'/movimientos/sync/rechazados';
        $vaerrors = array();
        $stm_movimiento = '';
        $ind_grabacion = false;    

        DB::table('moviTemporalesOK')->where('stm_movimiento', '>', $lastUpdate)
            ->orderBy('stm_movimiento', 'asc')
            ->chunk(100, function($movimientos) use ($url, &$stm_movimiento, &$vaerrors) {
                foreach($movimientos as $movimiento) {
                    $vvrespuesta = self::sendMovimientosSync($url, $movimiento);
                    if ($vvrespuesta[0] == Response::HTTP_OK) {
                        $stm_movimiento = $vvrespuesta[1];
                    } else {
                        $vaerrors[] = $vvrespuesta;
                    }
                }
            });
        
        if ($stm_movimiento !== '') {
            $ind_grabacion = true;
            $lastUpdate = $stm_movimiento;
        }

        return array('ind_grabacion' => $ind_grabacion, 'lastUpdate' => $lastUpdate, 'errors' => $vaerrors);
    }

    public function setPermanentesSync(Request $request) {

        $vvrespuesta = array();
        $PermanenteOK = new PermanenteOK;
        $PermanenteOK->stm_movimiento = $request['stm_movimiento'];
        $PermanenteOK->cod_credencial = $request['cod_credencial'];
        $PermanenteOK->ref_credencial = $request['ref_credencial'];
        $PermanenteOK->tipo_credencial = $request['tipo_credencial'];
        $PermanenteOK->cod_persona = $request['cod_persona'];
        $PermanenteOK->nom_persona = $request['nom_persona'];
        $PermanenteOK->ape_persona = $request['ape_persona'];
        $PermanenteOK->nro_documento = $request['nro_documento'];
        $PermanenteOK->cod_sector = $request['cod_sector'];
        $PermanenteOK->cod_sexo = $request['cod_sexo'];
        $PermanenteOK->cod_tipo_doc = $request['cod_tipo_doc'];
        $PermanenteOK->cod_tema_origen = $request['cod_tema_origen'];
        $PermanenteOK->ind_movimiento = $request['ind_movimiento'];
        $PermanenteOK->cod_persona_contacto = $request['cod_persona_contacto'];
        $PermanenteOK->nom_persona_contacto = $request['nom_persona_contacto'];
        $PermanenteOK->ape_persona_contacto = $request['ape_persona_contacto'];
        $PermanenteOK->cod_ou_contacto = $request['cod_ou_contacto'];
        $PermanenteOK->nom_ou_contacto = $request['nom_ou_contacto'];
        $PermanenteOK->obs_habilitacion = $request['obs_habilitacion'];                
        $PermanenteOK->aud_stm_ingreso = $request['aud_stm_ingreso'];
        $PermanenteOK->aud_usuario_ingreso = $request['aud_usuario_ingreso'];
        $PermanenteOK->aud_ip_ingreso = $request['aud_ip_ingreso'];
        try {
            $PermanenteOK->save();            
            $vvrespuesta = response($request['stm_movimiento'], Response::HTTP_OK);
        } catch (\Exception $e) {
            $vvrespuesta = response($e->getMessage(), Response::HTTP_CONFLICT);
        }
        
        return $vvrespuesta;
    }    

    public function setTemporalesSync(Request $request) {

        $vvrespuesta = array();
        $TemporalesOK = new TemporalOK;
        $TemporalesOK->stm_movimiento = $request['stm_movimiento'];
        $TemporalesOK->cod_credencial = $request['cod_credencial'];
        $TemporalesOK->ref_credencial = $request['ref_credencial'];
        $TemporalesOK->tipo_credencial = $request['tipo_credencial'];
        $TemporalesOK->cod_persona = $request['cod_persona'];
        $TemporalesOK->nom_persona = $request['nom_persona'];
        $TemporalesOK->ape_persona = $request['ape_persona'];
        $TemporalesOK->nro_documento = $request['nro_documento'];
        $TemporalesOK->cod_sector = $request['cod_sector'];
        $TemporalesOK->cod_sexo = $request['cod_sexo'];
        $TemporalesOK->cod_tipo_doc = $request['cod_tipo_doc'];
        $TemporalesOK->cod_tema_origen = $request['cod_tema_origen'];
        $TemporalesOK->ind_movimiento = $request['ind_movimiento'];
        $TemporalesOK->cod_persona_contacto = $request['cod_persona_contacto'];
        $TemporalesOK->nom_persona_contacto = $request['nom_persona_contacto'];
        $TemporalesOK->ape_persona_contacto = $request['ape_persona_contacto'];
        $TemporalesOK->cod_ou_contacto = $request['cod_ou_contacto'];
        $TemporalesOK->nom_ou_contacto = $request['nom_ou_contacto'];
        $TemporalesOK->obs_habilitacion = $request['obs_habilitacion'];                
        $TemporalesOK->aud_stm_ingreso = $request['aud_stm_ingreso'];
        $TemporalesOK->aud_usuario_ingreso = $request['aud_usuario_ingreso'];
        $TemporalesOK->aud_ip_ingreso = $request['aud_ip_ingreso'];
        
        try {
            $TemporalesOK->save();            
            $vvrespuesta = response($request['stm_movimiento'], Response::HTTP_OK);
        } catch (\Exception $e) {
            $vvrespuesta = response($e->getMessage(), Response::HTTP_CONFLICT);
        }
        
        return $vvrespuesta;
    }

    public function setRechazadosSync(Request $request) {
        $vvrespuesta = array();
        $Rechazados = new Rechazado;
        $Rechazados->stm_movimiento = $request['stm_movimiento'];
        $Rechazados->cod_credencial = $request['cod_credencial'];
        $Rechazados->ref_credencial = $request['ref_credencial'];
        $Rechazados->tipo_credencial = $request['tipo_credencial'];
        $Rechazados->cod_persona = $request['cod_persona'];
        $Rechazados->nom_persona = $request['nom_persona'];
        $Rechazados->ape_persona = $request['ape_persona'];
        $Rechazados->nro_documento = $request['nro_documento'];
        $Rechazados->cod_sector = $request['cod_sector'];
        $Rechazados->cod_sexo = $request['cod_sexo'];
        $Rechazados->cod_tipo_doc = $request['cod_tipo_doc'];
        $Rechazados->cod_tema_origen = $request['cod_tema_origen'];
        $Rechazados->ind_movimiento = $request['ind_movimiento'];
        $Rechazados->ind_rechazo = $request['ind_rechazo'];
        $Rechazados->tipo_habilitacion = $request['tipo_habilitacion'];
        $Rechazados->obs_habilitacion = $request['obs_habilitacion'];              
        $Rechazados->aud_stm_ingreso = $request['aud_stm_ingreso'];
        $Rechazados->aud_usuario_ingreso = $request['aud_usuario_ingreso'];
        $Rechazados->aud_ip_ingreso = $request['aud_ip_ingreso'];
        
        try {
            $Rechazados->save();            
            $vvrespuesta = response($request['stm_movimiento'], Response::HTTP_OK);
        } catch (\Exception $e) {
            $vvrespuesta = response($e->getMessage(), Response::HTTP_CONFLICT);
        }
        
        return $vvrespuesta;
    }

    public static function EventoCred(Request $request)
    {
        /*
        $validator = Validator::make($request->all(), [
            'cod_tema' => 'required',
            'valor' => 'required',
        ], [
            'cod_tema.required'=> __("Debe ingresar código de componente"),
            'valor.required'=> __("Debe ingresar valor"),
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }
*/

        $cod_tema = $request->input('cod_tema');
        $msgtext = $request->input('msgtext');
        $cod_credencial = $request->input('cod_credencial');
        $cod_persona = $request->input('cod_persona');
        $tipo_habilitacion = $request->input('tipo_habilitacion');
        $ind_rechazo = $request->input('ind_rechazo');
        $stm_event = $request->input('stm_event');
        $nom_tema = $request->input('nom_tema');
        $nom_sector = $request->input('nom_sector');
        $ind_movimiento = $request->input('ind_movimiento');
        $ind_movimiento = $request->input('ind_movimiento');
        $nom_persona = $request->input('nom_persona');
        $ape_persona = $request->input('ape_persona');

        $context=array(
            'msgtext'=>$msgtext,
            'stm_event' =>$stm_event,
            'cod_credencial' => $cod_credencial, 
            'cod_tema' => $cod_tema,
            'ind_rechazo' => $ind_rechazo,
            'nom_tema' => $nom_tema,
            'nom_sector'=> $nom_sector,
            'ind_movimiento' =>$ind_movimiento
        );

        if ($tipo_habilitacion=="P" && $ind_rechazo=="") {
            $PermanenteOK = new PermanenteOK;
            $PermanenteOK->stm_movimiento = Carbon::parse($stm_event);
            $PermanenteOK->cod_credencial = $cod_credencial;
            $PermanenteOK->ref_credencial = "";
            $PermanenteOK->tipo_credencial = "";
            $PermanenteOK->cod_persona = $cod_persona;
            $PermanenteOK->nom_persona = $nom_persona;
            $PermanenteOK->ape_persona = $ape_persona;
            $PermanenteOK->nro_documento = "";
            $PermanenteOK->cod_sector = 0;
            $PermanenteOK->nom_sector = $nom_sector;
            $PermanenteOK->cod_sexo = "";
            $PermanenteOK->cod_tipo_doc = "";
            $PermanenteOK->cod_tema_origen = $cod_tema;
            $PermanenteOK->nom_tema = $nom_tema;
            $PermanenteOK->ind_movimiento = $ind_movimiento;
            $PermanenteOK->cod_persona_contacto = 0;
            $PermanenteOK->nom_persona_contacto = "";
            $PermanenteOK->ape_persona_contacto = "";
            $PermanenteOK->cod_ou_contacto = 0;
            $PermanenteOK->nom_ou_contacto = "";
            $PermanenteOK->obs_habilitacion = "";                
            try {
                PermanenteOK::addAuditoria($PermanenteOK, "RL");
                $PermanenteOK->save();            
            } catch (\Exception $e) {
                return response($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        $event_tipe= ($ind_rechazo=="") ? "info" : "error";
        Broadcast::driver('fast-web-socket')->broadcast(['pantalla'], $event_tipe,  $context);
        return response(['ok' => __('El evento externo :COD_TEMA fue procesado satisfactoriamente',['COD_TEMA'=>$cod_tema])], Response::HTTP_OK);
    }

}
