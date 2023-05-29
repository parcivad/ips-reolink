<?php

/*** Function to make a command call to the device
 * @param string $ip        ip of the device
 * @param string $token     Token of the user
 * @param string $cmd       Command that should be executed
 * @param string $action    Action type
 * @param array $param      requested params
 * @param bool $raw         raw return
 * @throws ErrorException
 */
function cmd(string $ip, string $token, string $cmd, string $action, array $param, bool $raw) {
    $header_array = array("Connection: keep-alive",'Content-Type: application/json');
    // build url
    $url = "http://" . $ip . "/api.cgi?cmd=" . $cmd . "&token=" . $token;

    $body = "[".json_encode(
            [
                "cmd" => explode("&", $cmd)[0],
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
    if ($response == null) throw new ErrorException("Can't access device on this ip", 200);
    if ($raw) return $response;

    $arrayResponse = json_decode($response, true)[0];

    // in case of error throw exception otherwise return response
    if (isset($arrayResponse["error"])) {
        // check for login errors
        if ($arrayResponse["error"]["rspCode"] == -6 || $arrayResponse["error"]["rspCode"]  == -7) {
            // reset session to trigger new login
            throw new ErrorException("session reset needed", 201);
        }
        // other error will be shown via exception
        throw new ErrorException($arrayResponse["error"]["detail"], $arrayResponse["error"]["rspCode"] * -1000);
    }

    return $arrayResponse;
}