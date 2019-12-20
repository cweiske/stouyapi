#!/usr/bin/env php
<?php
/**
 * Import games from a OUYA game data repository
 *
 * @link https://github.com/cweiske/ouya-game-data/
 * @author Christian Weiske <cweiske@cweiske.de>
 */
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
require_once __DIR__ . '/filters.php';
if (!isset($argv[1])) {
    error('Pass the path to a "folders" file with game data json files folder names');
}
$foldersFile = $argv[1];
if (!is_file($foldersFile)) {
    error('Given path is not a file: ' . $foldersFile);
}

$GLOBALS['packagelists']['cweiskepicks'] = [
    'de.eiswuxe.blookid2',
    'com.cosmos.babyloniantwins'
];

$wwwDir = __DIR__ . '/../www/';

$baseDir   = dirname($foldersFile);
$gameFiles = [];
foreach (file($foldersFile) as $line) {
    $line = trim($line);
    if (strlen($line)) {
        if (strpos($line, '..') !== false) {
            error('Path attack in ' . $folder);
        }
        $folder = $baseDir . '/' . $line;
        if (!is_dir($folder)) {
            error('Folder does not exist: ' . $folder);
        }
        $gameFiles = array_merge($gameFiles, glob($folder . '/*.json'));
    }
}

$games = [];
$count = 0;
foreach ($gameFiles as $gameFile) {
    $game = json_decode(file_get_contents($gameFile));
    if ($game === null) {
        error('JSON invalid at ' . $gameFile);
    }
    addMissingGameProperties($game);
    $games[$game->packageName] = $game;

    writeJson(
        'api/v1/details-data/' . $game->packageName . '.json',
        buildDetails($game)
    );
    /* this crashes babylonian twins
    writeJson(
        'api/v1/games/' . $game->packageName . '/purchases',
        "{}\n"
    );
    */

    writeJson(
        'api/v1/apps/' . $game->packageName . '.json',
        buildApps($game)
    );
    $latestRelease = $game->latestRelease;
    writeJson(
        'api/v1/apps/' . $latestRelease->uuid . '.json',
        buildApps($game)
    );

    writeJson(
        'api/v1/apps/' . $latestRelease->uuid . '-download.json',
        buildAppDownload($game, $latestRelease)
    );

    if ($count++ > 20) {
        //break;
    }
}

writeJson('api/v1/discover-data/discover.json', buildDiscover($games));
writeJson('api/v1/discover-data/home.json', buildDiscoverHome($games));


function buildDiscover(array $games)
{
    $data = [
        'title' => 'DISCOVER',
        'rows'  => [],
        'tiles' => [],
    ];

    addDiscoverRow(
        $data, 'Last Updated',
        filterLastUpdated($games, 10)
    );
    addDiscoverRow(
        $data, 'Best rated',
        filterBestRated($games, 10)
    );
    addDiscoverRow(
        $data, "cweiske's picks",
        filterByPackageNames($games, $GLOBALS['packagelists']['cweiskepicks'])
    );

    $players = [
        //1 => '1 player',
        2 => '2 players',
        3 => '3 players',
        4 => '4 players',
    ];
    addDiscoverRow($data, '# of players', $players);
    foreach ($players as $num => $title) {
        writeJson(
            'api/v1/discover-data/' . categoryPath($title) . '.json',
            buildDiscoverCategory($title, filterByPlayers($games, $num))
        );
    }

    $ages = getAllAges($games);
    natsort($ages);
    addDiscoverRow($data, 'Content rating', $ages);
    foreach ($ages as $num => $title) {
        writeJson(
            'api/v1/discover-data/' . categoryPath($title) . '.json',
            buildDiscoverCategory($title, filterByAge($games, $title))
        );
    }

    $genres = getAllGenres($games);
    sort($genres);
    addChunkedDiscoverRows($data, $genres, 'Genres');

    foreach ($genres as $genre) {
        writeJson(
            'api/v1/discover-data/' . categoryPath($genre) . '.json',
            buildDiscoverCategory($genre, filterByGenre($games, $genre))
        );
    }

    $abc = array_merge(range('A', 'Z'), ['Other']);
    addChunkedDiscoverRows($data, $abc, 'Alphabetical');
    foreach ($abc as $letter) {
        writeJson(
            'api/v1/discover-data/' . categoryPath($letter) . '.json',
            buildDiscoverCategory($letter, filterByLetter($games, $letter))
        );
    }

    return $data;
}

/**
 * A genre category page
 */
function buildDiscoverCategory($name, $games)
{
    $data = [
        'title' => $name,
        'rows'  => [],
        'tiles' => [],
    ];
    addDiscoverRow(
        $data, 'Last Updated',
        filterLastUpdated($games, 10)
    );
    addDiscoverRow(
        $data, 'Best rated',
        filterBestRated($games, 10)
    );

    usort(
        $games,
        function ($gameA, $gameB) {
            return strcmp($gameA->title, $gameB->title);
        }
    );
    $chunks = array_chunk($games, 4);
    foreach ($chunks as $chunkGames) {
        addDiscoverRow($data, '', $chunkGames);
    }

    return $data;
}

function buildDiscoverHome(array $games)
{
    //we do not want anything here for now
    $data = [
        'title' => 'home',
        'rows'  => [
            [
                'title' => 'FEATURED',
                'showPrice' => false,
                'ranked'    => false,
                'tiles'     => [],
            ]
        ],
        'tiles' => [],
    ];
    return $data;
}

/**
 * Build api/v1/apps/$packageName
 */
function buildApps($game)
{
    $latestRelease = $game->latestRelease;

    $product      = null;
    $gamePromoted = getPromotedProduct($game);
    if ($gamePromoted) {
        $product = [
            'type'          => 'entitlement',
            'identifier'    => $gamePromoted->identifier,
            'name'          => $gamePromoted->name,
            'description'   => $gamePromoted->description ?? '',
            'localPrice'    => $gamePromoted->localPrice,
            'originalPrice' => $gamePromoted->originalPrice,
            'percentOff'    => 0,
            'currency'      => $gamePromoted->currency,
        ];
    }

    // http://cweiske.de/ouya-store-api-docs.htm#get-https-devs-ouya-tv-api-v1-apps-xxx
    return [
        'app' => [
            'uuid'          => $latestRelease->uuid,
            'title'         => $game->title,
            'overview'      => $game->overview,
            'description'   => $game->description,
            'gamerNumbers'  => $game->players,
            'genres'        => $game->genres,

            'website'       => $game->website,
            'contentRating' => $game->contentRating,
            'premium'       => $game->premium,
            'firstPublishedAt' => $game->firstPublishedAt,

            'likeCount'     => $game->rating->likeCount,
            'ratingAverage' => $game->rating->average,
            'ratingCount'   => $game->rating->count,

            'versionNumber' => $latestRelease->name,
            'latestVersion' => $latestRelease->uuid,
            'md5sum'        => $latestRelease->md5sum,
            'apkFileSize'   => $latestRelease->size,
            'publishedAt'   => $latestRelease->date,
            'publicSize'    => $latestRelease->publicSize,
            'nativeSize'    => $latestRelease->nativeSize,

            'mainImageFullUrl' => $game->discover,
            'videoUrl'         => getFirstVideoUrl($game->media),
            'filepickerScreenshots' => getAllImageUrls($game->media),
            'mobileAppIcon'    => null,

            'developer'           => $game->developer->name,
            'supportEmailAddress' => $game->developer->supportEmail,
            'supportPhone'        => $game->developer->supportPhone,
            'founder'             => $game->developer->founder,

            'promotedProduct' => $product,
        ],
    ];
}

function buildAppDownload($game, $release)
{
    return [
        'app' => [
            'fileSize'      => $release->size,
            'version'       => $release->uuid,
            'contentRating' => $game->contentRating,
            'downloadLink'  => $release->url,
        ]
    ];
}

/**
 * Build /app/v1/details?app=org.example.game
 */
function buildDetails($game)
{
    $latestRelease = $game->latestRelease;

    $mediaTiles = [];
    if ($game->discover) {
        $mediaTiles[] = [
            'type' => 'image',
            'urls' => [
                'thumbnail' => $game->discover,
                'full'      => $game->discover,
            ],
        ];
    }
    foreach ($game->media as $medium) {
        if ($medium->type == 'image')  {
            $mediaTiles[] = [
                'type' => 'image',
                'urls' => [
                    'thumbnail' => $medium->thumb,
                    'full'      => $medium->url,
                ],
            ];
        } else {
            $mediaTiles[] = [
                'type' => 'video',
                'url'  => $medium->url,
            ];
        }
    }

    $buttons = [];
    if (isset($game->links->unlocked)) {
        $buttons[] = [
            'text' => 'Show unlocked',
            'url'  => 'ouya://launcher/details?app=' . $game->links->unlocked,
            'bold' => true,
        ];
    }

    $product      = null;
    $gamePromoted = getPromotedProduct($game);
    if ($gamePromoted) {
        $product = [
            'type'          => 'entitlement',
            'identifier'    => $gamePromoted->identifier,
            'name'          => $gamePromoted->name,
            'description'   => $gamePromoted->description ?? '',
            'localPrice'    => $gamePromoted->localPrice,
            'originalPrice' => $gamePromoted->originalPrice,
            'percentOff'    => 0,
            'currency'      => $gamePromoted->currency,
        ];
    }

    // http://cweiske.de/ouya-store-api-docs.htm#get-https-devs-ouya-tv-api-v1-details
    return [
        'type'             => 'Game',
        'title'            => $game->title,
        'description'      => $game->description,
        'gamerNumbers'     => $game->players,
        'genres'           => $game->genres,

        'suggestedAge'     => $game->contentRating,
        'premium'          => $game->premium,
        'inAppPurchases'   => $game->inAppPurchases,
        'firstPublishedAt' => strtotime($game->firstPublishedAt),
        'ccUrl'            => null,

        'rating' => [
            'count'   => $game->rating->count,
            'average' => $game->rating->average,
        ],

        'apk' => [
            'fileSize'    => $latestRelease->size,
            'nativeSize'  => $latestRelease->nativeSize,
            'publicSize'  => $latestRelease->publicSize,
            'md5sum'      => $latestRelease->md5sum,
            'filename'    => 'FIXME',
            'errors'      => '',
            'package'     => $game->packageName,
            'versionCode' => $latestRelease->versionCode,
            'state'       => 'complete',
        ],

        'version' => [
            'number'      => $latestRelease->name,
            'publishedAt' => strtotime($latestRelease->date),
            'uuid'        => $latestRelease->uuid,
        ],

        'developer' => [
            'name'    => $game->developer->name,
            'founder' => $game->developer->founder,
        ],

        'metaData' => [
            'key:rating.average',
            'key:developer.name',
            'key:suggestedAge',
            number_format($latestRelease->size / 1024 / 1024, 2, '.', '') . ' MiB',
        ],

        'tileImage'     => $game->discover,
        'mediaTiles'    => $mediaTiles,
        'mobileAppIcon' => null,
        'heroImage'     => [
            'url' => null,
        ],

        'promotedProduct' => $product,
        'buttons'         => $buttons,
    ];
}

function addChunkedDiscoverRows(&$data, $games, $title)
{
    $chunks = array_chunk($games, 4);
    $first = true;
    foreach ($chunks as $chunk) {
        addDiscoverRow(
            $data, $first ? $title : '',
            $chunk
        );
        $first = false;
    }
}

function addDiscoverRow(&$data, $title, $games)
{
    $row = [
        'title'     => $title,
        'showPrice' => false,
        'ranked'    => false,
        'tiles'     => [],
    ];
    foreach ($games as $game) {
        if (is_string($game)) {
            //category link
            $tilePos = count($data['tiles']);
            $data['tiles'][$tilePos] = buildDiscoverCategoryTile($game);

        } else {
            //game
            if (isset($game->links->original)) {
                //do not link unlocked games.
                // people an access them via the original games
                continue;
            }
            $tilePos = findTile($data['tiles'], $game->packageName);
            if ($tilePos === null) {
                $tilePos = count($data['tiles']);
                $data['tiles'][$tilePos] = buildDiscoverGameTile($game);
            }
        }
        $row['tiles'][] = $tilePos;
    }
    $data['rows'][] = $row;
}

function findTile($tiles, $packageName)
{
    foreach ($tiles as $pos => $tile) {
        if ($tile['package'] == $packageName) {
            return $pos;
        }
    }
    return null;
}

function buildDiscoverCategoryTile($title)
{
    return [
        'url'   => 'ouya://launcher/discover/' . categoryPath($title),
        'image' => '',
        'title' => $title,
        'type'  => 'discover'
    ];
}

function buildDiscoverGameTile($game)
{
    $latestRelease = $game->latestRelease;
    return [
        'gamerNumbers' => $game->players,
        'genres' => $game->genres,
        'url' => 'ouya://launcher/details?app=' . $game->packageName,
        'latestVersion' => [
            'apk' => [
                'md5sum' => $latestRelease->md5sum,
            ],
            'versionNumber' => $latestRelease->name,
            'uuid' => $latestRelease->uuid,
        ],
        'inAppPurchases' => $game->inAppPurchases,
        'promotedProduct' => null,
        'premium' => $game->premium,
        'type' => 'app',
        'package' => $game->packageName,
        'updated_at' => strtotime($latestRelease->date),
        'updatedAt' => $latestRelease->date,
        'title' => $game->title,
        'image' => $game->discover,
        'contentRating' => $game->contentRating,
        'rating' => [
            'count' => $game->rating->count,
            'average' => $game->rating->average,
        ],
    ];
}

function categoryPath($title)
{
    return str_replace(['/', '\\', ' ', '+'], '_', $title);
}

function getAllAges($games)
{
    $ages = [];
    foreach ($games as $game) {
        $ages[] = $game->contentRating;
    }
    return array_unique($ages);
}

function getAllGenres($games)
{
    $genres = [];
    foreach ($games as $game) {
        $genres = array_merge($genres, $game->genres);
    }
    return array_unique($genres);
}

function addMissingGameProperties($game)
{
    if (!isset($game->overview)) {
        $game->overview = null;
    }
    if (!isset($game->description)) {
        $game->description = '';
    }
    if (!isset($game->players)) {
        $game->players = [1];
    }
    if (!isset($game->genres)) {
        $game->genres = ['Unsorted'];
    }
    if (!isset($game->website)) {
        $game->website = null;
    }
    if (!isset($game->contentRating)) {
        $game->contentRating = 'Everyone';
    }
    if (!isset($game->premium)) {
        $game->premium = false;
    }
    if (!isset($game->firstPublishedAt)) {
        $game->firstPublishedAt = gmdate('c');
    }

    if (!isset($game->rating)) {
        $game->rating = new stdClass();
    }
    if (!isset($game->rating->likeCount)) {
        $game->rating->likeCount = 0;
    }
    if (!isset($game->rating->average)) {
        $game->rating->average = 0;
    }
    if (!isset($game->rating->count)) {
        $game->rating->count = 0;
    }

    $game->latestRelease = null;
    $latestReleaseTimestamp = 0;
    foreach ($game->releases as $release) {
        if (!isset($release->publicSize)) {
            $release->publicSize = 0;
        }
        if (!isset($release->nativeSize)) {
            $release->nativeSize = 0;
        }

        $releaseTimestamp = strtotime($release->date);
        if ($releaseTimestamp > $latestReleaseTimestamp) {
            $game->latestRelease    = $release;
            $latestReleaseTimestamp = $releaseTimestamp;
        }
    }
    if ($game->latestRelease === null) {
        error('No latest release for ' . $game->packageName);
    }

    if (!isset($game->media)) {
        $game->media = [];
    }

    if (!isset($game->developer->uuid)) {
        $game->developer->uuid = null;
    }
    if (!isset($game->developer->name)) {
        $game->developer->name = 'unknown';
    }
    if (!isset($game->developer->supportEmail)) {
        $game->developer->supportEmail = null;
    }
    if (!isset($game->developer->supportPhone)) {
        $game->developer->supportPhone = null;
    }
    if (!isset($game->developer->founder)) {
        $game->developer->founder = false;
    }
}

function getFirstVideoUrl($media)
{
    foreach ($media as $medium) {
        if ($medium->type == 'video') {
            return $medium->url;
        }
    }
    return null;
}

function getAllImageUrls($media)
{
    $imageUrls = [];
    foreach ($media as $medium) {
        if ($medium->type == 'image') {
            $imageUrls[] = $medium->url;
        }
    }
    return $imageUrls;
}

function getPromotedProduct($game)
{
    if (!isset($game->products) || !count($game->products)) {
        return null;
    }
    foreach ($game->products as $gameProd) {
        if ($gameProd->promoted) {
            return $gameProd;
        }
    }
    return null;
}

function writeJson($path, $data)
{
    global $wwwDir;
    $fullPath = $wwwDir . $path;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(
        $fullPath,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

function error($msg)
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}
?>
