<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Middleware\ComunicacionDispositivos;
use App\Helpers\ChannelWriter;

//socat  pty,link=/dev/virtualcom0,raw  tcp:192.168.5.119:8080

class Rs485Daemon extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:Rs485Daemon
                            {--debug : Print debug information to console}
                            {--id_disp_origen= : Identificacion de origen ej 206}
                            {--io= : Identificacion del lector ej 1}
                            {--value= : lectura ej FFFFFFFF}
                            Ej: php artisan command:Rs485Daemon --id_disp_origen=218 --io=1 --value=9877187';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RS485 Processing daemon';

    /**
     * The console command description.
     *
     * @var json
     */
    protected $_device = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $_baudrate;
    protected $temp_dir = "";
    protected $_dHandle = "";
    protected $comdisp;
    protected $writeLog;
    protected $data_tmp;
    const logFileName = "serial485";
	const timetolog = 0;  //0.1
    protected $daemon_conf_ver="";
    const confVersion = "daemon_conf_ver";

    protected function printDebugInfo($text, $status = "info") {
        if ($this->option('debug')) {
            $this->writeLog->writeLog(self::logFileName, 'info', $text, array(), true, false);
        }
        return true;
    }

    public function sendCommand_RandWiegand($id_dispositivo,$io, $habilita) {
        $msg = sprintf("RS485 destino:%s comando:RandWiegand habilita:%s", $destino, $habilita);
        $this->printDebugInfo($msg);

        // 1 0 3 68 7 2 50 57 54 66 54 67 51 3 221 4
        $message = "              ";
        $message[0] = "\x01"; //Start
        $message[1] = chr($id_dispositivo);
        $message[2] = "\x00";
        $message[3] = "D";
        $message[4] = "\x05"; //Tamano paquete
        $message[5] = "\x02"; //Inicio
        if ($habilita)
            $message[6] = "\x01"; //   "\x11"; //paquete
        else
            $message[6] = "\x00"; //   "\x11"; //paquete
        $message[7] = "\x00";
        $message[8] = "\x00";
        $message[9] = "\x00";
        $message[10] = "\x00";
        $message[11] = "\x03"; //Fin
        $message[12] = "\x1B"; //CRC  Falta calcular CRC
        $message[13] = "\x04"; //Fin comando

        if (fwrite($this->_dHandle, $message) !== false)
            return true;
        else
            return false;
    }

    public function sendCommand($id_dispositivo,$io, $vadata) {
		if (!$vadata && empty($vadata))
			return true;

        // 1 0 3 68 7 2 50 57 54 66 54 67 51 3 221 4

        $message = "           ";
        $message[0] = "\x01"; //Start
        $message[1] = chr($id_dispositivo);
        $message[2] = "\x00";
        $message[3] = "A";
        $message[4] = "\x07"; //Tamano paquete
        $message[5] = "\x02"; //Inicio
        $message[6] = chr($vadata[0]); //   "\x11"; //paquete buzzer1
        $message[7] = chr($vadata[1]); // "\x12"; //paquete buzzer2
        $message[8] = chr($vadata[2]); // "\x13"; //paquete rele1
        $message[9] = chr($vadata[3]); // "\x14"; //paquete rele2
        $message[10] = chr($vadata[4]); // "\x15"; //paquete rele3
        $message[11] = chr($vadata[5]); // "\x16"; //paquete led1
        $message[12] = chr($vadata[6]); // "\x17"; //paquete led2
        $message[13] = "\x03"; //Fin
        $message[14] = "\x1B"; //CRC  Falta calcular CRC
        $message[15] = "\x04"; //Fin comando

        if (fwrite($this->_dHandle, $message) !== false)
            return true;
        else
            return false;
    }

    public function readPort($count = 0) {
        $data = "";
        $chr = "";
        $read = array($this->_dHandle);
        $write = NULL;
        $except = NULL;
        $break = false;

        if (false === ($num_changed_streams = stream_select($read, $write, $except, 1))) {
			$this->printDebugInfo("error select");

        } elseif ($num_changed_streams > 0) {

            do {
                $chr = stream_get_contents($this->_dHandle, 1);
                $this->data_tmp .= $chr;

                if ($chr == "\x04")
                    $break = true;
            } while ($chr != "" and $break != true);
        }

        if ($chr == "\x04") {
            $data = $this->data_tmp;
            $this->data_tmp = "";
        }

        return $data;
    }

    public function readPort_old($count = 0) {
        $content = "";
        $i = 0;

        if ($count !== 0) {
            do {
                if ($i > $count)
                    $content .= fread($this->_dHandle, ($count - $i));
                else
                    $content .= fread($this->_dHandle, 128);
            } while (($i += 128) === strlen($content));
        }
        else {
            /*
              do {
              $content.= fread($this->_dHandle, 128);
              usleep(100);
              } while (strpos($content,"\x04")===false );
             */

            $break = false;
            do {
                do {
                    $chr = fread($this->_dHandle, 1);
                    $content .= $chr;
                    if ($chr == "\x04" || feof($this->_dHandle))
                        $break = true;
                } while ($chr != "");
                usleep(100);
            } while ($break == false);
            if (feof($this->_dHandle))
                $this->connect_retry();
        }

        return $content;
    }

    protected function loadConfigData() {
        $this->id_dispositivo = ConfigParametro::get('ID_DISPOSITIVO',false);
        $this->_device = ConfigParametro::get('RS485_PORT', false);        
		$this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a ".$this->daemon_conf_ver);
	}
    
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        
        
 
        $this->temp_dir = sys_get_temp_dir();

        $this->comdisp = new ComunicacionDispositivos;
        $this->writeLog = new ChannelWriter();
        $this->loadConfigData();
        
        if ($this->option('id_disp_origen') and $this->option('io') and $this->option('value')) {
            $vacredencial = array(
				'id_disp_origen' => $this->option('id_disp_origen'),
                'io' => $this->option('io'),
                'value' => $this->option('value')
            );
            $foo = $this->comdisp->leeCredencial($vacredencial);
            if ($foo->status() == 200) {
				$respuesta = $foo->original['rs485'];
                echo "Resultado " . json_encode($respuesta) . "\n";
            }
            return;
        }

        $leeCredencialPerf = 0;
        $parseCommandPerf = 0;
        $sendCommandPerf = 0;
        $readdata="";
		$status="";
        $this->writeLog->writeLog("pantalla", 'info', "Conexión exitosa con " . $this->_device, array(), false, true);            
        $this->printDebugInfo("Conexión exitosa con " . $this->_device);

        $cmd= "/algo.exe";
        $timeleft = 1000;
        $descriptorspec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
        $pipes = array();

        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("proc_open failed on: " . $cmd);
        }

        do {
            $read = array($pipes[1]);
            stream_select($read, $write = NULL, $exeptions = NULL, $timeleft, NULL);
            if (!empty($read)) {
                $read_tmp .= fread($pipes[1], 8192);
            }
            
            if ($read_tmp == "") {
                continue;
            } else {  //Proceso comando
                $read = $read_tmp;
                $start = microtime(true);
                $data = $this->comdisp->parseCommand($read);
                $status = "ERROR";
                //$read = "";
                $parseCommandPerf = (microtime(true) - $start);
				$status="NONE";
				$readdata="";
				$leeCredencialPerf=0;
				$sendCommandPerf=0;
                if ($data != false) {
                    if ($data['comando'] == 'R') {
                        $start1 = microtime(true);
                        $foo = $this->comdisp->leeCredencial($data);
                        if ($foo->status() == 200) {
							$respuesta = $foo->original['rs485'];
                            $leeCredencialPerf = (microtime(true) - $start1);
                            $start2 = microtime(true);
                            $this->sendCommand($data['id_disp_origen'],$data['io'], $respuesta);
                            $sendCommandPerf = microtime(true) - $start2;
                            $status = "OK";
							$readdata=sprintf("%s %s %s %s Rta:%s", $data['id_disp_origen'], $data['io'],$data['comando'],  $data['value'], json_encode($respuesta));
                        } else {
							$readdata="leeCredencial: ".$foo->status();
						}
                    } else if ($data['comando'] == 'S') {
                        $status = "START";
						$start2 = microtime(true);
                        $this->sendCommand($data['id_disp_origen'],$data['io'], array(4,4,4,4,4,4,4));
                        $sendCommandPerf = microtime(true) - $start2;						
						
						$readdata=sprintf("%s %s %s %s", $data['id_disp_origen'], $data['io'], $data['comando'],  $data['value']);
                        $msg = sprintf("RS485 %s: %s , parseCommand:%f", $status, $readdata, $parseCommandPerf * 1000);
                        $this->writeLog->writeLog("pantalla", 'info', $msg, array(), false, true);
                    } else {
                        $status = "CMDFAIL";
						$readdata=strlen($read) . " " . bin2hex($read);
					}
				} else {
					$status="FAIL";
					$readdata=strlen($read) . " " . bin2hex($read);
				}
                $globalTimePerf = microtime(true) - $start;
                if($status!="OK" || $globalTimePerf>self::timetolog){
                    $msg = sprintf("RS485 %s: %s, parseCommand:%f, leeCredencialPerf:%f, sendCommandPerf:%f, globalTimePerf:%f", $status, $readdata,$parseCommandPerf * 1000, $leeCredencialPerf * 1000, $sendCommandPerf * 1000, $globalTimePerf * 1000);
                    $this->printDebugInfo($msg);
                }
            }
            
            
            
            
        } while (!feof($pipes[1]));
        
        proc_terminate($process);
//End Handle
}
