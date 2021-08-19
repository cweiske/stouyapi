<?php
/**
 * Return user data with dynamic username that has been saved during login
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 * @see    api/v1/sessions
 */
$dbFile  = __DIR__ . '/../../../../data/usernames.sqlite3';

$ip = $_SERVER['REMOTE_ADDR'];
if ($ip == '') {
    //empty ip
    header('X-Fail-Reason: empty ip address');
    header('Content-type: application/json');
    echo file_get_contents('me.json');
    exit(1);
}

try {
    $db = new SQLite3($dbFile, SQLITE3_OPEN_READONLY);
} catch (Exception $e) {
    //db file not found
    header('X-Fail-Reason: database file not found');
    header('Content-type: application/json');
    echo file_get_contents('me.json');
    exit(1);
}

$stmt = $db->prepare('SELECT * FROM usernames WHERE ip = :ip');
$stmt->bindValue(':ip', $ip);
$res = $stmt->execute();
$row = $res->fetchArray(SQLITE3_ASSOC);
$res->finalize();

if ($row === false) {
    header('Content-type: application/json');
    echo file_get_contents('me.json');
    exit();
}

$data = json_decode(file_get_contents('me.json'));
$data->gamer->username = $row['username'];

switch (strtolower($row['username'])) {
case 'cweiske':
case 'szeraax':
    $data->gamer->founder = true;
}

header('Content-type: application/json');
echo json_encode($data) . "\n";
?>
