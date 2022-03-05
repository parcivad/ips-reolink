<?php

require_once(dirname(__FILE__) . "/api.php");

//TODO: CLEAN THIS TRASH CODE UP AND MAKE THE WHOLE API CALL BETTER o.O

/** Function that should present a working token for the targeted device
 * @param string $ip IP-Address of the device
 * @param string $username User that should be used
 * @param string $password Password of the user
 * @return string Token
 * @throws ErrorException
 */
function getToken(string $ip, string $username, string $password): string {
    // get present data
    $session = json_decode(file_get_contents(dirname(__FILE__) . "/session.json" ),true);

    // token elapsed
    if (isset($session["token"]) && time() - $session["timestamp"] < $session["leaseTime"]) {
        return $session["token"];
    } else {
        // try to refresh the token
        try {
            // logout session
            userSessionLogout($ip);
            sleep(1);
            return userSessionLogin($ip, $username, $password);
        } catch (ErrorException $exception) {
            // giving the exception to the next function (ips modul function that can call an "error" status)
            throw new ErrorException($exception->getMessage(), $exception->getCode());
        }
    }
}

/*** Function to retrieve the
 * @param string $ip IP-Address of the device
 * @return void
 * @throws ErrorException
 */
function userSessionLogout(string $ip) {
    // get present data
    $session = json_decode(file_get_contents(dirname(__FILE__) . "/session.json" ),true);

    // check if token that should be logged out is present
    if (!$session["token"]) return;

    // send command to device
    cmd($ip, $session["token"], "Logout", 0, []);
    clear();
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
    $session = json_decode(file_get_contents(dirname(__FILE__) . "/session.json" ),true);

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

// Clears saved token and timestamp
function clear() {
    $session["token"] = null;
    $session["leaseTime"] = null;
    $session["timestamp"] = null;
    // save
    write($session);
}

/** Function to write the accessHandler.json
 * @param array $rewrite var to rewrite
 */
function write( array $rewrite ) {
    // rewrite file
    file_put_contents(dirname(__FILE__) . "/session.json", json_encode( $rewrite ));
}