<?php

function getToken(string $ip, string $username, string $password) {

}

/*** Function to retrieve the
 * @param string $ip IP-Address of the device
 * @param string $username User that should be forced to logout
 * @return void
 */
function userSessionLogout(string $ip, string $username) {
    //TODO: logout "user" and "decline" token from camera device
}

/*** Function to retrieve the
 * @param string $ip IP-Address of the device
 * @param string $username User that should be used for login
 * @param string $password Password of the user
 * @return void returns a token
 */
function userSessionLogin(string $ip, string $username, string $password) {
    //TODO: login with given user and return token
}