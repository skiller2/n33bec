<?php

namespace App\Http\Middleware;

use App\AptoFisico;
use App\Events\TemaEvent;
use App\HabiAcceso;
use App\HabiCredPersona;
use App\HabiCredSectores;
use Illuminate\Support\Facades\Broadcast;
use App\Helpers\ConfigParametro;
use App\Http\Controllers\Feriados;
use App\Http\Controllers\Sectores;
use App\MoviCredSector;
use App\MoviPersConCred;
use App\PermanenteOK;
use App\Rechazado;
use App\TemporalOK;
use App\Traits\LibGeneral;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use function event;
use function response;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class ComunicacionDispositivos {

    use LibGeneral;

    const logFileName = "dispositivos";
    protected $PermanenteOK;
    protected $TemporalOK;
    protected $Rechazado;
    protected $timezone;
    protected $conn;
    protected $lectores;
    protected $esquemas;
    protected $tipo_dias;
    protected $sectoresAF;
    protected $tema_local;
    protected $bits_wiegand;

    public function __construct() {
        $this->PermanenteOK = new PermanenteOK();
        $this->TemporalOK = new TemporalOK();
        $this->Rechazado = new Rechazado();

        $this->loadConfigData();

/*
        if (!Cache::get("EstadoHabiAccesoDispo")){
			HabiAcceso::checkhabiAcceso(false);
			Cache::forever("EstadoHabiAccesoDispo",true);
            $context=array(
                'msgtext' => __("Componentes actualizados"),
                "EstadoHabiAccesoDispo" => true, 
                "colorEstado" => "green"
            );
            Broadcast::driver('fast-web-socket')->broadcast(["estados"], 'error',  $context);
        }
*/
    }

    public function loadConfigData(){
        $this->timezone = ConfigParametro::get('TIMEZONE_INFORME', false);
        $this->tiempo_para_ingreso = ConfigParametro::get('TIEMPO_PARA_INGRESO', false);
        $this->max_ingresos_visita = ConfigParametro::get('MAX_INGRESOS_VISITA', false);
        $this->tipo_dias = ConfigParametro::get('TIPO_DIAS', true);
        $this->lectores = ConfigParametro::getTemas("LECTOR");
        $this->esquemas = ConfigParametro::getEsquemas();
        $this->sectoresAF = ConfigParametro::get('SECTORES_APTO_FISICO', true);
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $this->bits_wiegand = ConfigParametro::get('BITS_WIEGAND', false, 26);
    }

    public function parseCommand($read) {
        if (empty($read))
            return false;

        if (isset($read[0]) && ord($read[0]) != 1) {
            $read = substr($read, 1, 20);
        }
        if (isset($read[0]) && ord($read[0]) != 1) {
            $read = substr($read, 1, 20);
        }
        if (isset($read[0]) && ord($read[0]) != 1)
            return false;

        if (strlen($read) < 4)
            return false;


        $id_origen = ord($read[1]) * 256 + ord($read[2]);


        $comando = $read[3];
//        if ($comando != "D")
//            return false;

        $tambuffer = ord($read[4]);

        $buffer = substr($read, 5, $tambuffer + 1);

        $io_nro = substr($buffer, 1, 1);

        $value = substr($buffer, 2);

        if ($id_origen == "" or $io_nro == "" or $value == "") {
            return false;
        }
        $cod_tema="$id_origen/$comando/$io_nro";

        $value = hexdec($value);
        $resultado = array('cod_tema' => $cod_tema, 'valor' => $value, 'comando' => $comando, 'id_origen'=>$id_origen, 'io'=>$io_nro);
        return $resultado;
    }

    public function leeCredencial($data) {
        /**
         * "" = permitido, "N" = inválido, "R" = rechazado, "H" = tipo habilitación incorrecto, "S" = no existe Componente para credencial, 
         * "V" = no está vigente, "T" = tiempo de ingreso en temporal, "C" = cantidad ingresos en hab temporal, 
         * "F" = Apto fisico vencido, "G" = No posee apto fisico 
         */


        $ind_rechazo = "";
        $cod_persona = "0";
        $ref_credencial = "";
        $nom_persona = "";
        $ape_persona = "";
        $nro_documento = "";
        $cod_sexo = "";
        $cod_tipo_doc = "";
        $tipo_habilitacion = "";
        $obs_habilitacion = "";
        $des_credencial = "";
        $nom_ou_hab = "";
        $res_error = array();
        $stm_actual = Carbon::now();
        $cod_tema_origen = $data['cod_tema'];
        $cod_credencial = $data['valor']; //habiCredenciales
        $muestro_sector = false;
        $eliminar_visita = false;
        $ind_permanencia = false;

        //BUSCA SI EL Componente
        $tema_localizado = isset($this->lectores[$cod_tema_origen]);
        if (!$tema_localizado) {
            $context=array(
                'msgtext'=> __("Tema no localizado (:COD_TEMA_ORIGEN) valor :COD_CREDENCIAL",['COD_TEMA_ORIGEN'=>$cod_tema_origen,'COD_CREDENCIAL'=>$cod_credencial]),
                "cod_tema" => $cod_tema_origen, 
                "cod_credencial" => $cod_credencial
            );
            return response(['rs485' => array(),'context'=>$context,'event'=>'error','channel'=>'pantalla'], Response::HTTP_NOT_FOUND);
        }
        
        $ind_separa_facility_code = $this->lectores[$cod_tema_origen]['ind_separa_facility_code'];
        $res_error = $this->lectores[$cod_tema_origen]['res_error'];
        $res_ok = $this->lectores[$cod_tema_origen]['res_ok'];
        $ind_movimiento = $this->lectores[$cod_tema_origen]['ind_movimiento'];
        $tipo_credencial = $this->lectores[$cod_tema_origen]['tipo_credencial'];
        $tipo_habilitaciones = $this->lectores[$cod_tema_origen]['tipo_habilitaciones'];
        $des_movimiento = $this->lectores[$cod_tema_origen]['des_movimiento'];
        $cod_sector = $this->lectores[$cod_tema_origen]['cod_sector'];
        $info_sector = Sectores::getSectorInfoCache($cod_sector);
        $muestro_sector = $info_sector['nom_sector'];
        $ind_permanencia = $info_sector['ind_permanencia'];
        $nom_tema = $this->lectores[$cod_tema_origen]['nom_tema'];
        $tarjeta = $cod_credencial;

        //SI EL COMPONENTE EXISTE, ANALIZA SI SEPARA FACILITY CODE
        if ($ind_separa_facility_code == "1") {
            switch ($this->bits_wiegand) {
                case 35:
                    $bits_user_code = 20;
                    $fmt = "%'.04u%'.07u";
                    $fmt2 = "%'.04u-%'.07u";
                    $gen = bcdiv($cod_credencial, 1048576, 0);
                    break;
                    
                default:
                    $bits_user_code = 16;
                    $fmt = "%'.03u%'.05u";
                    $fmt2 = "%'.03u-%'.05u";
                    $gen = ($cod_credencial >> $bits_user_code);
                    break;
            }

            //Ojo en PHP 32bits >> falla
            $cod_cred_tmp = intval( $cod_credencial);
            $par = $cod_cred_tmp - (($cod_cred_tmp >> $bits_user_code) << $bits_user_code);

            $cod_credencial = sprintf($fmt, $gen, $par);
            $tarjeta = sprintf($fmt2, $gen, $par);

        }

        if ($ind_movimiento == "L") {
            $context=array(
                'msgtext'=> __("Tarjeta :TARJETA :DES_CREDENCIAL, Tema :NOM_TEMA, Mov :DES_MOVIMIENTO, Sector :MUESTRO_SECTOR",['NOM_TEMA'=> $nom_tema,'DES_MOVIMIENTO'=> $des_movimiento,'MUESTRO_SECTOR'=>$muestro_sector,'TARJETA'=>$tarjeta,'DES_CREDENCIAL' =>$des_credencial]),
                "cod_tema" => $cod_tema_origen,
                "cod_credencial" => $cod_credencial
            );
            return response(['rs485' => $res_ok,'context'=>$context,'event'=>'info','channel'=>'input'], Response::HTTP_OK);
        }



        $acceso = HabiAcceso::find($cod_credencial);
        if (!empty($acceso)) {
            $tipo_habilitacion = $acceso['tipo_habilitacion'];
            $stm_habilitacion_base = $acceso['stm_habilitacion'];
            $stm_habilitacion_hasta = $acceso['stm_habilitacion_hasta'];
            $cantidad_ingresos = (isset($acceso['cantidad_ingresos'])) ? $acceso['cantidad_ingresos'] : "0";

            if (!isset($tipo_habilitaciones[$tipo_habilitacion])) {
                $ind_rechazo = "H";
            }

            $cod_persona = (isset($acceso['cod_persona'])) ? $acceso['cod_persona'] : "0";
            $nom_persona = (isset($acceso['nom_persona'])) ? $acceso['nom_persona'] : "";
            $ape_persona = (isset($acceso['ape_persona'])) ? $acceso['ape_persona'] : "";
            $cod_sexo = (isset($acceso['cod_sexo'])) ? $acceso['cod_sexo'] : "";
            $nom_ou = (isset($acceso['nom_ou'])) ? $acceso['nom_ou'] : "";
            $cod_tipo_doc = (isset($acceso['cod_tipo_doc'])) ? $acceso['cod_tipo_doc'] : "";
            $nro_documento = (isset($acceso['nro_documento'])) ? $acceso['nro_documento'] : "";
            $cod_ou_hab = (isset($acceso['cod_ou_hab'])) ? $acceso['cod_ou_hab'] : "0";
            $nom_ou_hab = (isset($acceso['nom_ou_hab'])) ? $acceso['nom_ou_hab'] : "";
            $cod_persona_contacto = (isset($acceso['cod_persona_contacto'])) ? $acceso['cod_persona_contacto'] : "0";
            $nom_persona_contacto = (isset($acceso['nom_persona_contacto'])) ? $acceso['nom_persona_contacto'] : "";
            $ape_persona_contacto = (isset($acceso['ape_persona_contacto'])) ? $acceso['ape_persona_contacto'] : "";
            $stm_habilitacion = (isset($acceso['stm_habilitacion'])) ? $acceso['stm_habilitacion'] : "";
            $ref_credencial = (isset($acceso['ref_credencial'])) ? $acceso['ref_credencial'] : "";
            $des_credencial = ($ref_credencial == "") ? "" : "($ref_credencial)";
            $json_temas = (isset($acceso['json_temas'])) ? $acceso['json_temas'] : array();
            $cod_esquema_acceso = (isset($acceso['cod_esquema_acceso'])) ? $acceso['cod_esquema_acceso'] : "";
            $obs_habilitacion = (isset($acceso['obs_habilitacion'])) ? $acceso['obs_habilitacion'] : "";


            if ($ind_rechazo == "" && !isset($json_temas[$cod_tema_origen])) {
                $ind_rechazo = "S";
            }

            //SI ES PERMANENTE, CHEQUEA SI TIENE UNA FECHA DE HABILITACION HASTA 
            if ($ind_rechazo == "" && $tipo_habilitacion == "P" && $ind_movimiento == "I" && $stm_habilitacion_hasta) {
                $stm_habilitacion = new Carbon($stm_habilitacion_hasta);
                if ($stm_actual > $stm_habilitacion) {
                    $ind_rechazo = "V";
                }
            }
            //SI ES TEMPORAL, CHEQUEA QUE NO HAYA CADUCADO EL TIEMPO QUE TIENE PARA INGRESAR Y LA CANTIDAD MAXIMA DE INGRESOS
            else if ($ind_rechazo == "" && $tipo_habilitacion == "T" && $ind_movimiento == "I") {
                $stm_habilitacion = new Carbon($stm_habilitacion_base);
                if ($stm_actual > $stm_habilitacion->addMinutes($this->tiempo_para_ingreso)) {
                    $eliminar_visita = true;
                    $ind_rechazo = "T";
                }if ($cantidad_ingresos >= $this->max_ingresos_visita) {
                    $eliminar_visita = true;
                    $ind_rechazo = "C";
                }
            }

            //Validar esquema de acceso
            if($ind_rechazo==""){
                if(!isset($this->esquemas[$cod_esquema_acceso])) {
                    $ind_rechazo = "E";
                } else {
                    $stm_actual_local=$stm_actual->copy()->timezone($this->timezone);
                    $tipo_dia = ""; //H - N - M
					$tip_intervalos = "";
                    $dayOfWeek = $stm_actual_local->dayOfWeek;
                    $hora_actual = $stm_actual_local->format('H');
                    if (Feriados::isFeriado($stm_actual_local)) {
                        $tipo_dia = $this->tipo_dias["F"];
                    } else {
                        if (array_key_exists($dayOfWeek, (array)$this->tipo_dias))
                            $tipo_dia = $this->tipo_dias[$dayOfWeek];
						else 
							$tipo_dia = "X"; 
                    }
					
                    switch ($tipo_dia) {
                        case "H":
                            $tip_intervalos = "int_hab";
                            break;
                        case "N":
                            $tip_intervalos = "int_nohab";
                            break;
                        case "M":
                            $tip_intervalos = "int_mix";
                            break;
						default:
							$tip_intervalos = "int_nohab";
                    }
                    $obj_intervalos = $this->esquemas[$cod_esquema_acceso][$tip_intervalos];
                    $fec_habilitacion_hasta= $this->esquemas[$cod_esquema_acceso]['fec_habilitacion_hasta'];
                    if ($stm_actual->format('Y-m-d H:i:s.u') > $fec_habilitacion_hasta) {
                        $ind_rechazo = "E";
                    } else {
                        foreach ($obj_intervalos as $intervalo) {
                            $intervalo_d = $intervalo['d'];
                            $intervalo_h = $intervalo['h'];
                            if (!(($intervalo_d < $intervalo_h) && ($intervalo_d <= $hora_actual && $intervalo_h > $hora_actual)) || 
                                (($intervalo_d > $intervalo_h) && ($intervalo_d <= $hora_actual or $hora_actual < $intervalo_h))) {
                                    $ind_rechazo = "E";
                            }
                        }
                    }
                }
            }
        } else {
            $ind_rechazo = "R";
        }

        if ($ind_rechazo === '' && isset($this->sectoresAF[$cod_sector])) {
            $maesPersAF = AptoFisico::find($cod_persona);
            if (!$maesPersAF) {
                $ind_rechazo = "G";
            }
            else if ($maesPersAF->fec_vencimiento_af < $stm_actual->format('Y-m-d')) {
                $ind_rechazo = "F";
            }
        }

        //MOVI CRED SECTOR
        MoviCredSector::where('cod_credencial', $cod_credencial)->delete();

        //CREDENCIAL NO HABILITADA
        if ($ind_rechazo !== "") {
            if ($cod_credencial == 0xFFFFFFFF) {
                $ind_rechazo = "N";
            } else {
                $this->Rechazado->exists = false;
                $this->Rechazado->stm_movimiento = $stm_actual->format('Y-m-d H:i:s.u');
                $this->Rechazado->cod_credencial = $cod_credencial;
                $this->Rechazado->ref_credencial = $ref_credencial;
                $this->Rechazado->tipo_credencial = $tipo_credencial;
                $this->Rechazado->cod_persona = $cod_persona;
                $this->Rechazado->nom_persona = $nom_persona;
                $this->Rechazado->ape_persona = $ape_persona;
                $this->Rechazado->nro_documento = $nro_documento;
                $this->Rechazado->cod_sector = $cod_sector;
                $this->Rechazado->cod_sexo = $cod_sexo;
                $this->Rechazado->cod_tipo_doc = $cod_tipo_doc;
                $this->Rechazado->cod_tema_origen = $cod_tema_origen;
                $this->Rechazado->ind_movimiento = $ind_movimiento;
                $this->Rechazado->ind_rechazo = $ind_rechazo;
                $this->Rechazado->tipo_habilitacion = $tipo_habilitacion;
                $this->Rechazado->obs_habilitacion = $obs_habilitacion;
                $this->Rechazado->aud_stm_ingreso = $stm_actual;
                $this->Rechazado->aud_usuario_ingreso = "";
                $this->Rechazado->aud_ip_ingreso = "";
                $this->Rechazado->save();

                Cache::forever("LAST_ACCESS", array("cod_credencial" => $cod_credencial, "nom_ape_persona" => $nom_persona . " " . $ape_persona, "stm_access" => $stm_actual));
            }

            $event_data = array("ind_rechazo" => $ind_rechazo, "valor" => $cod_credencial, "des_valor" => "", 
                                "valor_analogico" => 0, "des_unidad_medida" => "CREDENCIAL", "des_persona" => $nom_persona . " " . $ape_persona);
            event(new TemaEvent($cod_tema_origen, $stm_actual, $event_data));
            
            if ($tipo_habilitacion == "T" && $eliminar_visita) {
                HabiCredSectores::where('cod_credencial', $cod_credencial)->delete();
                HabiCredPersona::where('cod_credencial', $cod_credencial)->delete();
                HabiAcceso::where('cod_credencial', $cod_credencial)->delete();
            }

            $context=array(
                'msgtext'=> __("Tarjeta :TARJETA :DES_CREDENCIAL, Persona :NRO_DOCUMENTO :APE_PERSONA :NOM_PERSONA, Organización :NOM_OU_HAB, Tema :NOM_TEMA, Mov :DES_MOVIMIENTO, Tipo Hab :TIPO_HABILITACION, Sector :MUESTRO_SECTOR (:IND_RECHAZO)",['TARJETA'=>$tarjeta, 'DES_CREDENCIAL'=>$des_credencial,'NRO_DOCUMENTO'=>$nro_documento, 'APE_PERSONA'=>$ape_persona,'NOM_PERSONA'=>$nom_persona,'NOM_OU_HAB'=> $nom_ou_hab,'NOM_TEMA'=>$nom_tema,'DES_MOVIMIENTO'=>$des_movimiento,'TIPO_HABILITACION'=>$tipo_habilitacion,'MUESTRO_SECTOR'=>$muestro_sector,'IND_RECHAZO'=>$ind_rechazo ]),
                'stm_event' =>$stm_actual,
                'cod_credencial' => $cod_credencial, 
                'cod_tema' => $cod_tema_origen,
                'ind_rechazo' => $ind_rechazo,
                'nom_tema' => $nom_tema,
                'nom_sector'=> $muestro_sector,
                'ind_movimiento' =>$ind_movimiento,
                'nom_persona' => $nom_persona,
                'ape_persona' => $ape_persona        
            );

            return response(['rs485' => $res_error, 'context'=>$context, 'event'=>'error','channel'=>'movcred'], Response::HTTP_OK);
        } else {
            
            if($tipo_habilitacion == "P"){
                
                $this->PermanenteOK->exists = false;
                $this->PermanenteOK->stm_movimiento = $stm_actual->format('Y-m-d H:i:s.u');
                $this->PermanenteOK->cod_credencial = $cod_credencial;
                $this->PermanenteOK->ref_credencial = $ref_credencial;
                $this->PermanenteOK->tipo_credencial = $tipo_credencial;
                $this->PermanenteOK->cod_persona = $cod_persona;
                $this->PermanenteOK->nom_persona = $nom_persona;
                $this->PermanenteOK->ape_persona = $ape_persona;
                $this->PermanenteOK->nro_documento = $nro_documento;
                $this->PermanenteOK->cod_sector = $cod_sector;
                $this->PermanenteOK->cod_sexo = $cod_sexo;
                $this->PermanenteOK->cod_tipo_doc = $cod_tipo_doc;
                $this->PermanenteOK->cod_tema_origen = $cod_tema_origen;
                $this->PermanenteOK->ind_movimiento = $ind_movimiento;
                $this->PermanenteOK->cod_persona_contacto = $cod_persona_contacto;
                $this->PermanenteOK->nom_persona_contacto = $nom_persona_contacto;
                $this->PermanenteOK->ape_persona_contacto = $ape_persona_contacto;
                $this->PermanenteOK->cod_ou_contacto = $cod_ou_hab;
                $this->PermanenteOK->nom_ou_contacto = $nom_ou_hab;
                $this->PermanenteOK->obs_habilitacion = $obs_habilitacion;                
                $this->PermanenteOK->aud_stm_ingreso = $stm_actual;
                $this->PermanenteOK->aud_usuario_ingreso = "";
                $this->PermanenteOK->aud_ip_ingreso = "";
                $this->PermanenteOK->save();
            }
            else if($tipo_habilitacion == "T"){
                
                $this->TemporalOK->exists = false;
                $this->TemporalOK->stm_movimiento = $stm_actual->format('Y-m-d H:i:s.u');
                $this->TemporalOK->cod_credencial = $cod_credencial;
                $this->TemporalOK->ref_credencial = $ref_credencial;
                $this->TemporalOK->tipo_credencial = $tipo_credencial;
                $this->TemporalOK->cod_persona = $cod_persona;
                $this->TemporalOK->nom_persona = $nom_persona;
                $this->TemporalOK->ape_persona = $ape_persona;
                $this->TemporalOK->nro_documento = $nro_documento;
                $this->TemporalOK->cod_sector = $cod_sector;
                $this->TemporalOK->cod_sexo = $cod_sexo;
                $this->TemporalOK->cod_tipo_doc = $cod_tipo_doc;
                $this->TemporalOK->cod_tema_origen = $cod_tema_origen;
                $this->TemporalOK->ind_movimiento = $ind_movimiento;
                $this->TemporalOK->cod_persona_contacto = $cod_persona_contacto;
                $this->TemporalOK->nom_persona_contacto = $nom_persona_contacto;
                $this->TemporalOK->ape_persona_contacto = $ape_persona_contacto;
                $this->TemporalOK->cod_ou_contacto = $cod_ou_hab;
                $this->TemporalOK->nom_ou_contacto = $nom_ou_hab;
                $this->TemporalOK->obs_habilitacion = $obs_habilitacion;
                $this->TemporalOK->aud_stm_ingreso = $stm_actual;
                $this->TemporalOK->aud_usuario_ingreso = "";
                $this->TemporalOK->aud_ip_ingreso = "";
                $this->TemporalOK->save();               
            }

            if ($ind_permanencia==1 && $ind_movimiento == "I") {
                $moviCredSector = new MoviCredSector;
                $moviCredSector->cod_credencial = $cod_credencial;
                $moviCredSector->cod_sector = $cod_sector;
                $moviCredSector->stm_ingreso = $stm_actual;
                $moviCredSector->aud_stm_ingreso = $stm_actual;
                $moviCredSector->aud_usuario_ingreso = "";
                $moviCredSector->aud_ip_ingreso = "";
                $moviCredSector->save();
            }
            

            $acceso->cantidad_ingresos = $cantidad_ingresos + 1;
            $acceso->save();

            Cache::forever("LAST_ACCESS", array("cod_credencial" => $cod_credencial, "nom_ape_persona" => $nom_persona . " " . $ape_persona, "stm_access" => $stm_actual));

            $event_data = array("ind_rechazo" => $ind_rechazo, "valor" => $cod_credencial, "des_valor" => "", 
                                "valor_analogico" => 0, "des_unidad_medida" => "CREDENCIAL", "des_persona" => $nom_persona . " " . $ape_persona);
            event(new TemaEvent($cod_tema_origen, $stm_actual, $event_data));

            if ($ind_movimiento == "E" && $tipo_habilitacion == "T") {
                HabiCredSectores::where('cod_credencial', $cod_credencial)->delete();
                HabiCredPersona::where('cod_credencial', $cod_credencial)->delete();
                HabiAcceso::where('cod_credencial', $cod_credencial)->delete();
                MoviPersConCred::where('cod_credencial', $cod_credencial)->delete();
            }
            if ($ind_movimiento == "E" && $tipo_habilitacion == "P") {
                $acceso->cantidad_ingresos = 0;
                $acceso->save();
            }
            $context = array(
                'stm_event' =>$stm_actual,
                'cod_credencial' => $cod_credencial,
                'cod_persona'=> $cod_persona,
                'tipo_habilitacion' => $tipo_habilitacion, 
                'cod_persona'=> $cod_persona,
                'cod_tema' => $cod_tema_origen, 
                'ind_rechazo' => $ind_rechazo,
                'ind_movimiento' =>$ind_movimiento,
                'nom_tema' => $nom_tema,
                'nom_sector'=> $muestro_sector,
                'nom_persona' => $nom_persona,
                'ape_persona' => $ape_persona
            );
            $context['msgtext']="Tarjeta: $tarjeta $des_credencial, Persona: $nro_documento $ape_persona $nom_persona, Organización: $nom_ou_hab, Tema: $nom_tema, Mov: $des_movimiento, Tipo Hab: $tipo_habilitacion, Sector: $muestro_sector";

            return response(['rs485' => $res_ok, 'context'=>$context, 'event'=>'info','channel'=>'movcred'], Response::HTTP_OK);
        }
    }

}
