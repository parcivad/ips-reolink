<?php

/***
 * @param string $ip IP of the device itself
 * @param string $token Token that should be used
 * @param string $cmd Command that should be executed
 * @param string $action Action type
 * @param array $param requested params
 * @return array
 * @throws ErrorException in case of error return from the api
 */
function cmd(string $ip, string $token, string $cmd, string $action, array $param): array {

    $header_array = array('Content-Type: application/json');
    // build url
    $url = "https://" . $ip . "/api.cgi?cmd=" . $cmd . "&token=" . $token;

    $body = json_encode([
       [
           "cmd" => $cmd,
           "action" => $action,
           "param" => $param
       ]
    ]);

    // configure curl options in array
    $curlOptions = array(
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $header_array,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
    );

    // initialize curl and call
    $ch = curl_init();
    curl_setopt_array($ch, $curlOptions);
    // ask
    $response = curl_exec($ch);
    // response url response saved
    curl_close($ch);

    $arrayResponse = json_decode($response, true)[0];

    // in case of error throw exception otherwise return response
    if (isset($arrayResponse["error"])) throw new ErrorException($arrayResponse["error"]["detail"], $arrayResponse["error"]["rspCode"]);
    return $arrayResponse;
}
