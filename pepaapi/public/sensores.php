<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Read the raw POST data
$json = file_get_contents('php://input');


// Decode the JSON data
$data = json_decode($json, true);
//file_put_contents("/tmp/salida.txt",$json." ".var_exporT($data,true)."\n",FILE_APPEND);

$origin = $data['origin'];
$button = $data['button'];

$dataOut = [
 "cod_tema" => "demo/sensor/$origin/alarma",
 "valor"=> $button
];

$url = 'http://localhost/api/v1/movieventos/evento';
$jsonDataPost = json_encode($dataOut);
//$jsonDataPost = $dataOut;.


$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonDataPost);
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonDataPost),
        'Locale: it'
));

$response = curl_exec($curl);

curl_close($curl);

exit();
?>

