<?php

$processList="supervisorctl status ";
$processStart="supervisorctl start ";
$dhcpcdrestart="dhcpcd -n";


function printDebugInfo($text,$status="info")
{
    $debug=false;
    if ($debug){
      echo "$text\n";
      //$this->output->writeln("<$status>[".Carbon::now()->format('Y-m-d H:i:s')."] :</$status> ".$text);
    }
    return true;
}

function main($processList,$processStart,$dhcpcdrestart) {
    while (1){
        exec($processList,$output,$returnvalue);
        foreach ($output as $key => $value) {
            if (strpos($value,"FATAL")!==false){
                $output_start="";
                $vaLine = explode(" ", $value);
                exec($processStart." ".$vaLine[0],$output_start);
                printDebugInfo("Reinicando ".$vaLine[0]." ".var_export($output_start,true));
            }
        }

        sleep(5*60);
    }
}

printDebugInfo("Iniciando monitoreo");
main($processList,$processStart,$dhcpcdrestart);

?>