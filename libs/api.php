<?php

/***
 * @param string $ip IP of the device itself
 * @param string $token Token that should be used
 * @param string $cmd Command that should be executed
 * @param string $action Action type
 * @param array $param requested params
 * @throws ErrorException in case of error return from the api
 */
function cmd(string $ip, string $token, string $cmd, string $action, array $param): array {

    $header_array = array('Content-Type: application/json');
    // build url
    $url = "http://" . $ip . "/api.cgi?cmd=" . $cmd . "&token=" . $token;

    $body = "[".json_encode(
       [
           "cmd" => $cmd,
           "action" => $action,
           "param" => $param
       ]
    )."]";

    // configure curl options in array
    $curlOptions = array(
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $header_array,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true
    );

    // initialize curl and call
    $ch = curl_init();
    curl_setopt_array($ch, $curlOptions);
    // ask
    $response = curl_exec($ch);
    // response url response saved
    curl_close($ch);

    // check if there is any response
    if (!isset($response)) throw new ErrorException("Can't reach device with the given IP", 200);

    $arrayResponse = json_decode($response, true)[0];

    // in case of error throw exception otherwise return response
    if (isset($arrayResponse["error"])) {
        // check for login errors
        if ($arrayResponse["error"]["rspCode"] = -6 || $arrayResponse["error"]["rspCode"]  = -7) {
            // clear token because it's not valid anymore
            file_put_contents(dirname(__FILE__) . "/session.json", json_encode( ["token"=> null, "leaseTime"=> null, "timestamp"=> null]));
        }
        // other error will be shown via exception
        throw new ErrorException($arrayResponse["error"]["detail"], $arrayResponse["error"]["rspCode"] * -1000);
    }
    return $arrayResponse;
}
