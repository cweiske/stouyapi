<?php
/**
 * Delete a game from the "push to my OUYA" list
 *
 * Pushes are stored in the sqlite3 database in push-to-my-ouya.php
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 */
$dbFile     = __DIR__ . '/../../../data/push-to-my-ouya.sqlite3';
$apiGameDir = __DIR__ . '/details-data/';

require_once __DIR__ . '/../../../src/push-to-my-ouya-helpers.php';

$ip = $_SERVER['REMOTE_ADDR'];
if ($ip == '') {
    //empty ip
    header('X-Fail-Reason: empty ip address');
    header('HTTP/1.0 204 No Content');
    exit(1);
}
$ip = mapIp($ip);

if (!isset($_GET['game'])) {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Game parameter missing' . "\n";
    exit(1);
}

$game = $_GET['game'];
$cleanGame = preg_replace('#[^a-zA-Z0-9._]#', '', $game);
if ($game != $cleanGame || $game == '') {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Invalid game' . "\n";
    exit(1);
}

try {
    $db = new SQLite3($dbFile, SQLITE3_OPEN_READWRITE);
} catch (Exception $e) {
    //db file not found
    header('X-Fail-Reason: database file not found');
    header('HTTP/1.0 204 No Content');
    exit(1);
}

$rowId = $db->querySingle(
    'SELECT id FROM pushes'
    . ' WHERE ip = \'' . SQLite3::escapeString($ip) . '\''
    . ' AND game =\'' . SQLite3::escapeString($game) . '\''
);
if ($rowId === null) {
    header('HTTP/1.0 404 Not Found');
    header('Content-type: text/plain');
    echo 'Game not queued' . "\n";
    exit(1);
}

$db->exec('DELETE FROM pushes WHERE id = ' . intval($rowId));
header('HTTP/1.0 204 No Content');
?>
