<?php
/**
 * Store the desired username during the login process
 *
 * It will be read by the Razer Forge TV when calling api/v1/gamers/me.
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 * @see    api/razer/session
 * @see    api/v1/gamers/me
 */

if (!isset($_POST['email'])) {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: application/json');
    echo '{"error":{"message":"E-Mail missing","code": 2001}}' . "\n";
    exit(1);
}
$email = $_POST['email'];

//we use the ouya username storage code here
// and simply use the part before the @ in the e-mail as username.
list($_POST['username']) = explode('@', $email);
require __DIR__ . '/../v1/sessions.php';
