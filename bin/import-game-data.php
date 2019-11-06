#!/usr/bin/env php
<?php
/**
 * Import games from a OUYA game data repository
 *
 * @link https://github.com/cweiske/ouya-game-data/
 * @author Christian Weiske <cweiske@cweiske.de>
 */
if (!isset($argv[1])) {
    error('Pass the path to a directory with game data json files');
}
$gameDataDir = $argv[1];
if (!is_dir($gameDataDir)) {
    error('Given path is not a directory: ' . $gameDataDir);
}

$wwwDir = __DIR__ . '/../www/';

$gameFiles = glob($gameDataDir . '/*.json');
$games = [];
foreach ($gameFiles as $gameFile) {
    $game = json_decode(file_get_contents($gameFile));
    if ($game === null) {
        error('JSON invalid at ' . $gameFile);
    }
    addMissingGameProperties($game);
    //FIXME: add missing properties?
    $games[$game->package] = $game;
    writeJson(
        'api/v1/apps/' . $game->package,
        buildApps($game)
    );
    writeJson(
        'api/v1/details-data/' . $game->package . '.json',
        buildDetails($game)
    );
    //exit(2);
    
}

//FIXME: build discover section

/**
 * Build api/v1/apps/$package
 */
function buildApps($game)
{
    $latestRelease = getLatestRelease($game);
    
    // http://cweiske.de/ouya-store-api-docs.htm#get-https-devs-ouya-tv-api-v1-apps-xxx
    return [
        'app' => [
            'uuid'          => $game->uuid,
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

            'mainImageFullUrl' => $game->media->large,
            'videoUrl'         => $game->media->video,
            'filepickerScreenshots' => $game->media->screenshots,
            'mobileAppIcon'    => null,

            'developer'           => $game->developer->name,
            'supportEmailAddress' => $game->developer->supportEmail,
            'supportPhone'        => $game->developer->supportPhone,
            'founder'             => $game->developer->founder,

            'promotedProduct' => null,
        ],
    ];
}

/**
 * Build /app/v1/details?app=org.example.game
 */
function buildDetails($game)
{
    $latestRelease = getLatestRelease($game);

    $mediaTiles = [];
    if ($game->media->large) {
        $mediaTiles[] = [
            'type' => 'image',
            'urls' => [
                'thumbnail' => $game->media->large,
                'full'      => $game->media->large,
            ],
            'fp_url' => $game->media->large,
        ];
    }
    if ($game->media->video) {
        $mediaTiles[] = [
            'type' => 'video',
            'url'  => $game->media->video,
        ];
    }
    foreach ($game->media->screenshots as $screenshot) {
        $mediaTiles[] = [
            'type' => 'image',
            'urls' => [
                'thumbnail' => $screenshot,
                'full'      => $screenshot,
            ],
            'fp_url' => $screenshot,
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
            'package'     => $game->package,
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

        'tileImage'     => $game->media->discover,
        'mediaTiles'    => $mediaTiles,
        'mobileAppIcon' => null,
        'heroImage'     => [
            'url' => null,
        ],
        
        'promotedProduct' => null,
    ];             
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

    foreach ($game->releases as $release) {
        if (!isset($release->publicSize)) {
            $release->publicSize = 0;
        }
        if (!isset($release->nativeSize)) {
            $release->nativeSize = 0;
        }
    }

    if (!isset($game->media->video)) {
        $game->media->video = null;
    }
    if (!isset($game->media->screenshots)) {
        $game->media->screenshots = [];
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

function getLatestRelease($game)
{
    $latestRelease = null;
    foreach ($game->releases as $release) {
        if ($release->latest ?? false) {
            $latestRelease = $release;
            break;
        }
    }
    if ($latestRelease === null) {
        error('No latest release for ' . $game->package);
    }
    return $latestRelease;
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
        json_encode($data, JSON_PRETTY_PRINT) . "\n"
    );
}

function error($msg)
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}
?>
