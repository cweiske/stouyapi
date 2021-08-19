<?php
/**
 * Store the desired username during the login process
 *
 * It will be read by the ouya when calling api/v1/gamers/me.
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 * @see    api/v1/sessions
 * @see    api/v1/gamers/me
 */
$dbFile  = __DIR__ . '/../../../data/usernames.sqlite3';

if (!isset($_POST['username'])) {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: application/json');
    echo '{"error":{"message":"Username missing","code": 2001}}' . "\n";
    exit(1);
}
$username = $_POST['username'];

$ip = $_SERVER['REMOTE_ADDR'];
if ($ip == '') {
    header('HTTP/1.0 400 Bad Request');
    header('Content-type: application/json');
    echo '{"error":{"message":"Cannot detect your IP address","code": 2002}}'
        . "\n";
    exit(1);
}

try {
    $db = new SQLite3($dbFile, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal server error');
    header('Content-type: application/json');
    echo '{"error":{"message":"Cannot open username database","code": 2003}}'
        . "\n";
    echo $e->getMessage() . "\n";
    exit(2);
}

$res = $db->querySingle(
    'SELECT name FROM sqlite_master WHERE type = "table" AND name = "usernames"'
);
if ($res === null) {
    //table does not exist yet
    $db->exec(
        <<<SQL
        CREATE TABLE usernames (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            ip TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
SQL
    );
}

//clean up old usernames
$db->exec(
    'DELETE FROM usernames'
    . ' WHERE created_at < \'' . gmdate('Y-m-d H:i:s', time() - 86400) . '\''
);

//clean up previous logins
$stmt = $db->prepare('DELETE FROM usernames WHERE ip = :ip');
$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
$stmt->execute()->finalize();

//store the username
$stmt = $db->prepare('INSERT INTO usernames (ip, username) VALUES(:ip, :username)');
$stmt->bindValue(':ip', $ip);
$stmt->bindValue(':username', $username);
$res = $stmt->execute();
if ($res === false) {
    header('HTTP/1.0 500 Internal server error');
    header('Content-type: application/json');
    echo '{"error":{"message":"Cannot store username","code": 2004}}'
        . "\n";
    exit(3);
}
$res->finalize();

header('HTTP/1.0 200 OK');
header('Content-type: application/json');
require __DIR__ . '/sessions';
?>
