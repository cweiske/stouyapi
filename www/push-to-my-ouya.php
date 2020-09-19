<?php
/**
 * Click "push to my OUYA" in the browser, and the OUYA will install
 * the game a few minutes later.
 *
 * Works without registration.
 * We simply use the IP address as user identification.
 * Pushed games are deleted after 24 hours.
 * Maximal 30 games per IP to prevent flooding.
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 * @see    api/v1/queued_downloads.php
 * @see    api/v1/queued_downloads_delete.php
 */
$dbFile     = __DIR__ . '/../data/push-to-my-ouya.sqlite3';
$apiGameDir = __DIR__ . '/api/v1/details-data/';

require_once __DIR__ . '/../src/push-to-my-ouya-helpers.php';

//support different ipv4-only domain
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo 'POST only, please' . "\n";
    exit(1);
}

if (!isset($_GET['game'])) {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo '"game" parameter missing' . "\n";
    exit(1);
}

$game = $_GET['game'];
$cleanGame = preg_replace('#[^a-zA-Z0-9._]#', '', $game);
if ($game != $cleanGame) {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Invalid game' . "\n";
    exit(1);
}

$apiGameFile = $apiGameDir . $game . '.json';
if (!file_exists($apiGameFile)) {
    header('HTTP/1.0 404 Not Found');
    header('Content-type: text/plain');
    echo 'Game does not exist' . "\n";
    exit(1);
}

$ip = $_SERVER['REMOTE_ADDR'];
if ($ip == '') {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Cannot detect your IP address' . "\n";
    exit(1);
}
$ip = mapIp($ip);

try {
    $db = new SQLite3($dbFile, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal server error');
    header('Content-type: text/plain');
    echo 'Cannot open database' . "\n";
    echo $e->getMessage() . "\n";
    exit(2);
}

$res = $db->querySingle(
    'SELECT name FROM sqlite_master WHERE type = "table" AND name = "pushes"'
);
if ($res === null) {
    //table does not exist yet
    $db->exec(
        <<<SQL
        CREATE TABLE pushes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game TEXT NOT NULL,
            ip TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
SQL
    );
}

//clean up old pushes
$db->exec(
    'DELETE FROM pushes'
    . ' WHERE created_at < \'' . gmdate('Y-m-d H:i:s', time() - 86400) . '\''
);

//check if this IP already pushed this game
$numThisGame = $db->querySingle(
    'SELECT COUNT(*) FROM pushes'
    . ' WHERE ip = \'' . SQLite3::escapeString($ip) . '\''
    . ' AND game = \'' . SQLite3::escapeString($game) . '\''
);
if ($numThisGame >= 1) {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Already pushed.' . "\n";
    exit(1);
}

//check number of pushes for this IP
$numPushes = $db->querySingle(
    'SELECT COUNT(*) FROM pushes'
    . ' WHERE ip = \'' . SQLite3::escapeString($ip) . '\''
);
if ($numPushes >= 30) {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Too many pushes. Come back tomorrow.' . "\n";
    exit(1);
}

//store the push
$stmt = $db->prepare('INSERT INTO pushes (game, ip) VALUES(:game, :ip)');
$stmt->bindValue(':game', $game);
$stmt->bindValue(':ip', $ip);
$res = $stmt->execute();
if ($res === false) {
    header('HTTP/1.0 500 Internal server error');
    header('Content-type: text/plain');
    echo 'Cannot store push' . "\n";
    exit(3);
}
$res->finalize();

header('HTTP/1.0 200 OK');
header('Content-type: text/plain');
echo 'Push accepted' . "\n";
exit(3);
?>
