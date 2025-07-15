<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;


/**
 * Description of LogParser
 *
 * @author fpl
 */
class ProcessorMetrics extends Controller
{
    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "detalle":
                return "ab_gestion";
            default:
                return "";
        }
    }

    public function getData()
    {
        $version = self::getVersion();
        $cpuLoad = self::getCpuLoad();
        $cpuTemp = self::getCpuTemp();
        $gpuTemp = self::getGpuTemp();
        $cpuFrequency = self::getCpuFrequency();

        return array("version"=>$version,"cpuLoad"=>$cpuLoad,"cpuTemp"=>$cpuTemp,"gpuTemp"=>$gpuTemp,"cpuFrequency"=>$cpuFrequency);
    }

    public static function getVersion()
    {
        try{
            $cpuinfo = preg_split ("/\n/", @file_get_contents('/proc/cpuinfo'));
            foreach ($cpuinfo as $line) {
                if (preg_match('/Revision\s*:\s*([^\s]*)\s*/', $line, $matches)) {
                    return hexdec($matches[1]);
                }
            }
        }
        catch(\Exception $exc){}

        return 0;
    }

    public static function getCpuLoad()
    {
        $cpuLoad = 0;
        $cant_cpu = 1;

        if(function_exists("sys_getloadavg")){
            $cant_cpu = intval(@file_get_contents('/sys/devices/system/cpu/kernel_max'));
            $cant_cpu++;
            $cpuLoadavg = sys_getloadavg();
            $cpuLoad = floatval($cpuLoadavg[0])*100/$cant_cpu;
        } else {
          exec('wmic cpu get LoadPercentage', $p);
          $cpuLoad=$p[1];
        }

        return number_format($cpuLoad, 2, '.', '');
    }

    public static function getCpuTemp($fahrenheit = false)
    {
        $cputemp = 0;
        try{
            $cputemp = floatval(@file_get_contents('/sys/class/thermal/thermal_zone0/temp'))/1000;
            $cputemp = number_format($cputemp, 2, '.', '');
            if($fahrenheit)
                $cputemp = 1.8* $cputemp+32;
        }
        catch(\Exception $e){}

        return $cputemp;
    }

    public static function getGpuTemp($fahrenheit = false)
    {
        $gputemp = 0;
        try{
            $gputemp = floatval(str_replace(array('temp=', '\'C'), '', exec('/opt/vc/bin/vcgencmd measure_temp')));
            if($fahrenheit)
                $gputemp = 1.8* $gputemp+32;
        }
        catch(\Exception $e){}

        return $gputemp;
    }

    public static function getCpuFrequency()
    {
        $cpu_freq = array();
        try{
            $cant_cpu = @file_get_contents('/sys/devices/system/cpu/kernel_max');
            for($i=0;$i<=$cant_cpu;$i++){
                $frequency = floatval(@file_get_contents('/sys/devices/system/cpu/cpu'.$i.'/cpufreq/scaling_cur_freq'))/1000;
                if ($frequency>0)
                    $cpu_freq[$i] = $frequency;
            }

        }
        catch(\Exception $e){
            $cpu_freq = array("0"=>1200,"1"=>1200,"2"=>1200,"3"=>1200);
        }

        return $cpu_freq;
    }

}
