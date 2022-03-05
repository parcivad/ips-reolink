<?php

require_once(dirname(__FILE__) . "/api.php");

function getToken(string $ip, string $username, string $password) {
    // get present data
    $session = json_decode(file_get_contents(dirname(__FILE__) . "session.json" ),true);
}

/*** Function to retrieve the
 * @param string $ip IP-Address of the device
 * @throws ErrorException
 */
function userSessionLogout(string $ip) {
    // get present data
    $session = json_decode(file_get_contents(dirname(__FILE__) . "session.json" ),true);

    // check if token that should be logged out is present
    if (!$session["token"]) return true;

    // send command to device
    $rsp = cmd($ip, $session["token"], "Logout", 0, []);

    $session["token"] = null;
    $session["leaseTime"] = null;
    $session["timestamp"] = null;
    // save
    write($session);
}

/*** Function to retrieve the
 * @param string $ip IP-Address of the device
 * @param string $username User that should be used for login
 * @param string $password Password of the user
 * @return string returns a token
 * @throws ErrorException
 */
function userSessionLogin(string $ip, string $username, string $password): string {
    // get present data
    $session = json_decode(file_get_contents(dirname(__FILE__) . "session.json" ),true);

    $param = [
        "User" => [
            "userName" => $username,
            "password" => $password
        ]
    ];

    // send command to device
    $rsp = cmd($ip,"", "Login", 0, $param);

    $session["token"] = $rsp["value"]["Token"]["name"];
    $session["leaseTime"] = $rsp["value"]["Token"]["leaseTime"];
    $session["timestamp"] = time();
    // save
    write($session);
    return $rsp["value"]["Token"]["name"];
}

/** Function to write the accessHandler.json
 * @param array $rewrite var to rewrite
 */
function write( array $rewrite ) {
    // rewrite file
    file_put_contents(dirname(__FILE__) . "/accessHandler.json", json_encode( $rewrite ));
}