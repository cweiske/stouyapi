<?php
/**
 * List games from the "push to my OUYA" list
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
    header('Content-type: application/json');
    echo file_get_contents('queued_downloads');
    exit(1);
}
$ip = mapIp($ip);

try {
    $db = new SQLite3($dbFile, SQLITE3_OPEN_READONLY);
} catch (Exception $e) {
    //db file not found
    header('X-Fail-Reason: database file not found');
    header('Content-type: application/json');
    echo file_get_contents('queued_downloads');
    exit(1);
}

$res = $db->query(
    'SELECT * FROM pushes'
    . ' WHERE ip = \'' . SQLite3::escapeString($ip) . '\''
);
$queue = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $apiGameFile = $apiGameDir . $row['game'] . '.json';
    if (!file_exists($apiGameFile)) {
        //game deleted?
        continue;
    }
    $json = json_decode(file_get_contents($apiGameFile));
    $queue[] = [
        'versionUuid' => '',
        'title'       => $json->title,
        'source'      => 'gamer',
        'uuid'        => $row['game'],
    ];
}

header('Content-type: application/json');
echo json_encode(['queue' => $queue]) . "\n";
?>
