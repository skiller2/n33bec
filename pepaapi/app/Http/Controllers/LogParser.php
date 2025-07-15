<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Psr\Log\LogLevel;
use ReflectionClass;
use Auth;
use function storage_path;
/**
 * Description of LogParser
 *
 * @author fpl
 */
class LogParser extends Controller
{
    public static function getAbility($metodo)
    {
        switch ($metodo){
            case "index":
                return "ab_gestion";
            default:
                return "";
        }
    }
    /**
     * @var string file
     */
    private static $file;

    private static $levels_classes = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'danger',
        'critical' => 'danger',
        'alert' => 'danger',
        'emergency' => 'danger',
    ];

    private static $levels_imgs = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'warning',
        'critical' => 'warning',
        'alert' => 'warning',
        'emergency' => 'warning',
    ];

    public function getLogList()
    {
    //    $user = $JWTAuth->parseToken()->toUser();
        $user = Auth::user();

        foreach(self::getFiles(true) as $index=>$logfile){
            $logList[] = array("id"=>$index,"name"=>$index);
        }
        Cache::put('loglist_'.$user['cod_usuario'],$logList);
        return $logList;
    }

    public function index($log_id,$posicion)
    {
        $log_levels = self::getLogLevels();
        $log = array();
        $bloque = 1024*30;
        $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*/';
        $logfiles = self::getFiles(false);

        if(!count($logfiles)) {
            return false;
        }
        if(!$log_id)
            $log_id = key($logfiles);

        self::$file = $logfiles[$log_id];
        $filesize = filesize(self::$file);

        if($posicion == 0)
            $posicion = $filesize - $bloque;
        if($posicion<0)
            $posicion = 0;
        //if (app('files')->size(self::$file) > self::MAX_FILE_SIZE) return null;
        //$file = app('files')->get(self::$file);
        $fp = fopen(self::$file, 'r');
        fseek($fp, $posicion);
        $file = fread($fp, $bloque);

        $posicion += strlen($file);

        //$headings = explode("\n",$headings);

        preg_match_all($pattern, $file, $headings);

        if (!is_array($headings)) return $log;

        $log_data = preg_split($pattern, $file);

        if ($log_data[0] < 1) {
            array_shift($log_data);
        }

        foreach ($headings as $h) {
            for ($i=0, $j = count($h); $i < $j; $i++) {
                foreach ($log_levels as $level_key => $level_value) {
                    if (strpos(strtolower($h[$i]), '.' . $level_value)) {

                        preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?(\w+)\.' . $level_key . ': (.*?)( in .*?:[0-9]+)?$/', $h[$i], $current);

                        if (!isset($current[3])) continue;

                        $log[] = array(
                            'context' => $current[2],
                            'level' => $level_value,
                            'level_class' => self::$levels_classes[$level_value],
                            'level_img' => self::$levels_imgs[$level_value],
                            'Timestamp' => $current[1],
                            'Line' => $current[3]
                        );
                    }
                }
            }
        }


        fclose($fp);

        //$log['Timestamp'] = 2015071602553825;
        //$log['Line'] = "2015-07-16 21:55:38.2576|INFO||This is information.";
        return array("data"=>$log,"posicion"=>$posicion);
    }

    const MAX_FILE_SIZE = 52428800; // Why? Uh... Sorry

    /**
     * @param string $file
     */
    public static function setFile($file)
    {
        $file = self::pathToLogFile($file);

        if (app('files')->exists($file)) {
            self::$file = $file;
        }
    }

    public static function pathToLogFile($file)
    {
        $logsPath = storage_path('logs');

        if (app('files')->exists($file)) { // try the absolute path
            return $file;
        }

        $file = $logsPath . '/' . $file;

        // check if requested file is really in the logs directory
        if (dirname($file) !== $logsPath) {
            throw new \Exception('No such log file');
        }

        return $file;
    }

    /**
     * @return string
     */
    public static function getFileName()
    {
        return basename(self::$file);
    }


    /**
     * @param bool $basename
     * @return array
     */
    public static function getFiles($resetCache)
    {
        //$token = JWTAuth::getToken();
        //$user = JWTAuth::toUser($token);
        $user = array('cod_usuario'=>"test"); //TODO
        if(Cache::has('logfiles_'.$user['cod_usuario']) && !$resetCache){
            $logfiles = Cache::get('logfiles_'.$user['cod_usuario']);
            return $logfiles;
        }else{
            $logfiles = array();
            $files = glob(storage_path() . '/logs/*');
            $files = array_reverse($files);
            $files = array_filter($files, 'is_file');
            if (is_array($files)) {
                foreach ($files as $file) {
                    $logfiles[basename($file,".log")] = $file;
                }
            }

//file_put_contents("c:/temp/archivo.txt",var_export($user['cod_usuario'],true));
            Cache::put('logfiles_'.$user['cod_usuario'],$logfiles);
            return $logfiles;
        }
    }

    /**
     * @return array
     */
    private static function getLogLevels()
    {
        $class = new ReflectionClass(new LogLevel);
        return $class->getConstants();
    }
}
