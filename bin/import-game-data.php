#!/usr/bin/env php
<?php
/**
 * Import games from a OUYA game data repository and generate static API files
 *
 * @link https://github.com/cweiske/ouya-game-data/
 * @author Christian Weiske <cweiske@cweiske.de>
 */
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filters.php';

//command line option parsing
$optind = null;
$opts = getopt('h', ['help', 'mini', 'noqr'], $optind);
$args = array_slice($argv, $optind);

if (isset($opts['help']) || isset($opts['h'])) {
    echo "Import games from a OUYA game data repository\n";
    echo "\n";
    echo "Usage: import-game-data.php [--mini] [--noqr] [--help|-h]\n";
    echo " --mini  Generate small but ugly JSON files\n";
    echo " --noqr  Do not generate and link QR code images\n";
    exit(0);
}

if (!isset($args[0])) {
    error('Pass the path to a "folders" file with game data json files folder names');
}
$foldersFile = $args[0];
if (!is_file($foldersFile)) {
    error('Given path is not a file: ' . $foldersFile);
}

$cfgMini     = isset($opts['mini']);
$cfgEnableQr = !isset($opts['noqr']);


//default configuration values
$GLOBALS['baseUrl']      = 'http://ouya.cweiske.de/';
$GLOBALS['categorySubtitles'] = [];
$GLOBALS['packagelists'] = [];
$GLOBALS['urlRewrites']  = [];
$cfgFile = __DIR__ . '/../config.php';
if (file_exists($cfgFile)) {
    include $cfgFile;
}

$wwwDir = __DIR__ . '/../www/';

if ($cfgEnableQr) {
    $qrDir = $wwwDir . 'gen-qr/';
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0775);
    }
}

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

//store git repository version of last folder
$workdir = getcwd();
chdir($folder);
$gitDate = `git log --max-count=1 --format="%h %cI"`;
chdir($workdir);
file_put_contents($wwwDir . '/game-data-version', $gitDate);

$games = [];
$count = 0;
$developers = [];

//load game data. doing early to collect a developer's games
foreach ($gameFiles as $gameFile) {
    $game = json_decode(file_get_contents($gameFile));
    if ($game === null) {
        error('JSON invalid at ' . $gameFile);
    }
    addMissingGameProperties($game);
    $games[$game->packageName] = $game;

    if (!isset($developers[$game->developer->uuid])) {
        $developers[$game->developer->uuid] = [
            'info'      => $game->developer,
            'products'  => [],
            'gameNames' => [],
        ];
    }
    $developers[$game->developer->uuid]['gameNames'][] = $game->packageName;
}

//write json api files
foreach ($games as $game) {
    $products = $game->products ?? [];
    foreach ($products as $product) {
        writeJson(
            'api/v1/developers/' . $game->developer->uuid
            . '/products/' . $product->identifier . '.json',
            buildDeveloperProductOnly($product, $game->developer)
        );
        $developers[$game->developer->uuid]['products'][] = $product;
    }

    writeJson(
        'api/v1/details-data/' . $game->packageName . '.json',
        buildDetails(
            $game,
            count($developers[$game->developer->uuid]['gameNames']) > 1
        )
    );
    if ($game->relationships->launcher ?? false) {
        //yes, this is no UUID. Should work anyway.
        $game->launcherBundleUuid = $game->packageName . '-lb';
        if (!isset($game->relationshipObjects)) {
            $game->relationshipObjects = (object) [];
        }
        $game->relationshipObjects->launcher = $games[$game->relationships->launcher];
        writeJson(
            'api/v1/details-data/' . $game->launcherBundleUuid . '.json',
            buildDetailsLauncherBundle(
                $game,
                $games[$game->relationships->launcher]
            )
        );
    }

    writeJson(
        'api/v1/games/' . $game->packageName . '/purchases',
        buildPurchases($game)
    );

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

calculateRank($games);

foreach ($developers as $developer) {
    writeJson(
        //index.htm does not need a rewrite rule
        'api/v1/developers/' . $developer['info']->uuid
        . '/products/index.htm',
        buildDeveloperProducts($developer['products'], $developer['info'])
    );
    writeJson(
        'api/v1/developers/' . $developer['info']->uuid
        . '/current_gamer',
        buildDeveloperCurrentGamer()
    );

    if (count($developer['gameNames']) > 1) {
        writeJson(
            'api/v1/discover-data/dev--' . $developer['info']->uuid . '.json',
            buildSpecialCategory(
                'Developer: ' . $developer['info']->name,
                filterByPackageNames($games, $developer['gameNames'])
            )
        );
    }
}

$data = buildDiscover($games);
writeJson('api/v1/discover-data/discover.json', $data);
writeJson('api/v1/discover-data/discover.forge.json', convertCategoryToForge($data));
writeJson('api/v1/discover-data/home.json', buildDiscoverHome($games));

//make
writeJson(
    'api/v1/discover-data/tutorials.json',
    buildMakeCategory('Tutorials', filterByGenre($games, 'Tutorials'))
);

$searchLetters = 'abcdefghijklmnopqrstuvwxyz0123456789., ';
foreach (str_split($searchLetters) as $letter) {
    $letterGames = filterBySearchWord($games, $letter);
    writeJson(
        'api/v1/search-data/' . $letter . '.json',
        buildSearch($letterGames)
    );
}


function buildDiscover(array $games)
{
    $games = removeMakeGames($games);
    $data = [
        'title' => 'DISCOVER',
        'rows'  => [],
        'tiles' => [],
    ];

    addDiscoverRow(
        $data, 'New games',
        filterLastAdded($games, 10)
    );
    addDiscoverRow(
        $data, 'Best rated games',
        filterBestRatedGames($games, 10),
        true
    );

    foreach ($GLOBALS['packagelists'] as $listTitle => $listPackageNames) {
        addDiscoverRow(
            $data, $listTitle,
            filterByPackageNames($games, $listPackageNames)
        );
    }

    addDiscoverRow(
        $data, 'Special',
        [
            'Best rated',
            'Best rated games',
            'Most rated',
            'Random',
            'Last updated',
        ]
    );
    writeCategoryJson(
        'api/v1/discover-data/' . categoryPath('Best rated') . '.json',
        buildSpecialCategory('Best rated', filterBestRated($games, 99))
    );
    writeCategoryJson(
        'api/v1/discover-data/' . categoryPath('Best rated games') . '.json',
        buildSpecialCategory('Best rated games', filterBestRatedGames($games, 99))
    );
    writeCategoryJson(
        'api/v1/discover-data/' . categoryPath('Most rated') . '.json',
        buildSpecialCategory('Most rated', filterMostDownloaded($games, 99))
    );
    writeCategoryJson(
        'api/v1/discover-data/' . categoryPath('Random') . '.json',
        buildSpecialCategory(
            'Random ' . date('Y-m-d H:i'),
            filterRandom($games, 99)
        )
    );
    writeCategoryJson(
        'api/v1/discover-data/' . categoryPath('Last updated') . '.json',
        buildSpecialCategory('Last updated', filterLastUpdated($games, 99))
    );

    $players = [
        //1 => '1 player',
        2 => '2 players',
        3 => '3 players',
        4 => '4 players',
    ];
    addDiscoverRow($data, 'Multiplayer', $players);
    foreach ($players as $num => $title) {
        writeCategoryJson(
            'api/v1/discover-data/' . categoryPath($title) . '.json',
            buildDiscoverCategory(
                $title,
                //I do not want emulators here,
                // and neither Streaming apps
                filterByGenre(
                    filterByGenre(
                        filterByPlayers($games, $num),
                        'Emulator', true
                    ),
                    'App', true
                )
            )
        );
    }

    $ages = getAllAges($games);
    natsort($ages);
    addDiscoverRow($data, 'Content rating', $ages);
    foreach ($ages as $num => $title) {
        writeCategoryJson(
            'api/v1/discover-data/' . categoryPath($title) . '.json',
            buildDiscoverCategory($title, filterByAge($games, $title))
        );
    }

    $genres = removeMakeGenres(getAllGenres($games));
    sort($genres);
    addChunkedDiscoverRows($data, $genres, 'Genres');

    foreach ($genres as $genre) {
        writeCategoryJson(
            'api/v1/discover-data/' . categoryPath($genre) . '.json',
            buildDiscoverCategory($genre, filterByGenre($games, $genre))
        );
    }

    $abc = array_merge(range('A', 'Z'), ['Other']);
    addChunkedDiscoverRows($data, $abc, 'Alphabetical');
    foreach ($abc as $letter) {
        writeCategoryJson(
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
    if (isset($GLOBALS['categorySubtitles'][$name])) {
        $data['stouyapi']['subtitle'] = $GLOBALS['categorySubtitles'][$name];
    }

    if (count($games) >= 20) {
        addDiscoverRow(
            $data, 'Last updated',
            filterLastUpdated($games, 10)
        );
        addDiscoverRow(
            $data, 'Best rated',
            filterBestRated($games, 10),
            true
        );
    }

    $games = sortByTitle($games);
    $chunks = array_chunk($games, 4);
    $title = 'All';
    foreach ($chunks as $chunkGames) {
        addDiscoverRow($data, $title, $chunkGames);
        $title = '';
    }

    return $data;
}

/**
 * Modify a category to make it suitable for the Razer Forge TV
 *
 * - Fold rows without title into the previous row
 * - Remove automatically generated categories ("Last updated", "Best rated")
 *
 * @see buildDiscoverCategory()
 */
function convertCategoryToForge($data, $removeAutoCategories = false)
{
    //merge tiles from rows without title into the previous row
    $lastTitleRowId = null;
    foreach ($data['rows'] as $rowId => $row) {
        if ($row['title'] !== '') {
            $lastTitleRowId = $rowId;
        } else if ($lastTitleRowId !== null) {
            $data['rows'][$lastTitleRowId]['tiles'] = array_merge(
                $data['rows'][$lastTitleRowId]['tiles'],
                $row['tiles']
            );
            unset($data['rows'][$rowId]);
        }
    }

    if ($removeAutoCategories) {
        foreach ($data['rows'] as $rowId => $row) {
            if ($row['title'] === 'Last updated'
                || $row['title'] === 'Best rated'
            ) {
                unset($data['rows'][$rowId]);
            }
        }
    }

    $data['rows'] = array_values($data['rows']);

    return $data;
}

function buildMakeCategory($name, $games)
{
    $data = [
        'title' => $name,
        'rows'  => [],
        'tiles' => [],
    ];

    $games = sortByTitle($games);
    addDiscoverRow($data, '', $games);

    return $data;
}

/**
 * Category without the "Last updated" or "Best rated" top rows
 *
 * Used for "Best rated", "Most rated", "Random"
 */
function buildSpecialCategory($name, $games)
{
    $data = [
        'title' => $name,
        'rows'  => [],
        'tiles' => [],
    ];

    $first3 = array_slice($games, 0, 3);
    $chunks = array_chunk(array_slice($games, 3), 4);
    array_unshift($chunks, $first3);

    foreach ($chunks as $chunkGames) {
        addDiscoverRow($data, '', $chunkGames);
    }

    return $data;
}

function buildDiscoverHome(array $games)
{
    $data = [
        'title' => 'home',
        'rows'  => [
        ],
        'tiles' => [],
    ];

    if (isset($GLOBALS['home'])) {
        reset($GLOBALS['home']);
        $title = key($GLOBALS['home']);
        addDiscoverRow(
            $data, $title,
            filterByPackageNames($games, $GLOBALS['home'][$title])
        );
    } else {
        $data['rows'][] = [
            'title'     => 'FEATURED',
            'showPrice' => false,
            'ranked'    => false,
            'tiles'     => [],
        ];
    }

    return $data;
}

/**
 * Build api/v1/apps/$packageName
 *
 * Detail page in discover store
 */
function buildApps($game)
{
    $latestRelease = $game->latestRelease;

    $product      = null;
    $gamePromoted = getPromotedProduct($game);
    if ($gamePromoted) {
        $product = buildProduct($gamePromoted);
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

function buildProduct($product)
{
    if ($product === null) {
        return null;
    }
    return [
        'type'          => $product->type ?? 'entitlement',
        'identifier'    => $product->identifier,
        'name'          => $product->name,
        'description'   => $product->description ?? '',
        'localPrice'    => $product->localPrice,
        'originalPrice' => $product->originalPrice,
        'priceInCents'  => $product->originalPrice * 100,
        'percentOff'    => 0,
        'currency'      => $product->currency,
    ];
}

/**
 * Build /app/v1/details?app=org.example.game
 *
 * Detail page for already installed game.
 */
function buildDetails($game, $linkDeveloperPage = false): array
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
                    'thumbnail' => $medium->thumb ?? $medium->url,
                    'full'      => $medium->url,
                ],
            ];
        } else {
            if (!isUnsupportedVideoUrl($medium->url)) {
                $mediaTiles[] = [
                    'type' => 'video',
                    'url'  => $medium->url,
                ];
            }
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
        $product = buildProduct($gamePromoted);
    }

    $iaUrl = null;
    if (isset($game->latestRelease->url)
        && substr($game->latestRelease->url, 0, 29) == 'https://archive.org/download/'
    ) {
        $iaUrl = dirname($game->latestRelease->url) . '/';
    }

    $description = $game->description;
    if (in_array('Launcher', $game->genres)) {
        $description = 'Note:'
            . ' This is only a starter app for the PlayJam GameStick game.'
            . " The game must be installed, too.\n\n"
            . $description;
    }
    if (isset($game->notes) && trim($game->notes)) {
        $description = "Technical notes:\r\n" . $game->notes
            . "\r\n----\r\n"
            . $description;
    }

    // http://cweiske.de/ouya-store-api-docs.htm#get-https-devs-ouya-tv-api-v1-details
    $data = [
        'type'             => 'Game',
        'title'            => $game->title,
        'description'      => $description,
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

        'stouyapi' => [
            'internet-archive' => $iaUrl,
            'developer-url'    => $game->developer->website ?? null,
            'blockedInWebText' => $game->blockedInWebText ?? null,
        ]
    ];

    if ($linkDeveloperPage) {
        $data['developer']['url'] = 'ouya://launcher/discover/dev--'
            . categoryPath($game->developer->uuid);
    }

    return $data;
}

/**
 * Build /app/v1/details?page=org.example.game-lb
 *
 * Launcher bundle detail page (GameStick game + launcher)
 */
function buildDetailsLauncherBundle(object $game, object $launcher): array
{
    $gameDetails     = buildDetails($game, false);
    $launcherDetails = buildDetails($launcher, false);

    $promotedProduct = getPromotedProduct($game);

    $data = [
        'title'       => $game->title . ' LB',
        'description' => "Bundle: GameStick game + OUYA launcher\n\n"
            . $game->description,
        'muted'       => true,//???

        'metaData' => [
            $game->title,
            $launcher->title,
        ],
        'mediaTiles' => $gameDetails['mediaTiles'],

        'apps' => [],
        'buttons' => [],

        'bundle' => [
            'apps' => [
                buildDetailsLauncherBundleApp($game),
                buildDetailsLauncherBundleApp($launcher),
            ],
            'currency'      => $promotedProduct->currency ?? 'EUR',
            'price'         => $promotedProduct->originalPrice ?? 0,
            'contentRating' => $game->contentRating,
            'purchased'     => true,
            'games' => [
                $game->packageName,
                $launcher->packageName,
            ],
            'purchaseUrl' => 'ouya://launcher/purchase?developer=' . $launcher->developer->uuid . '&product=' . $game->launcherBundleUuid,
        ],

        'stouyapi' => [
            'tileImage' => $game->discover,
        ],
    ];

    return $data;
}

/**
 * Build a bundle->app[] entry for a game bundle detail page
 */
function buildDetailsLauncherBundleApp(object $game): array
{
    $latestRelease = $game->latestRelease;
    return [
        'gamerNumbers' => $game->players,
        'genres'       => removeLauncherGenre($game->genres),
        'url' => 'ouya://launcher/details?app=' . $game->packageName,
        'latestVersion' => [
            'versionNumber' => $latestRelease->name,
            'uuid'          => $latestRelease->uuid,
            'apk'           => [
                'md5sum'    => $latestRelease->md5sum,
            ],
        ],
        'inAppPurchases'  => $game->inAppPurchases,
        'promotedProduct' => buildProduct(getPromotedProduct($game)),
        'premium'         => $game->premium,
        'type'            => 'app',
        'package'         => $game->packageName,
        'updated_at' => strtotime($latestRelease->date),
        'updatedAt' => $latestRelease->date,
        'title' => $game->title,
        'image' => $game->discover,
        'contentRating' => $game->contentRating,
        'rating' => [
            'count'   => $game->rating->count,
            'average' => $game->rating->average,
        ],
    ];
}

function buildDeveloperCurrentGamer()
{
    return [
        'gamer' => [
            'uuid'     => '00702342-0000-1111-2222-c3e1500cafe2',
            'username' => 'stouyapi',
        ],
    ];
}

/**
 * For /api/v1/developers/xxx/products/?only=yyy
 */
function buildDeveloperProductOnly($product, $developer)
{
    return [
        'developerName' => $developer->name,
        'currency'      => $product->currency,
        'products'      => [
            buildProduct($product),
        ],
    ];
}

/**
 * For /api/v1/developers/xxx/products/
 */
function buildDeveloperProducts($products, $developer)
{
    //remove duplicates
    $products = array_values(array_column($products, null, 'identifier'));

    $jsonProducts = [];
    foreach ($products as $product) {
        $jsonProducts[] = buildProduct($product);
    }
    return [
        'developerName' => $developer->name,
        'currency'      => $products[0]->currency ?? 'EUR',
        'products'      => $jsonProducts,
    ];
}

function buildPurchases($game)
{
    $purchasesData = [
        'purchases' => [],
    ];
    $promotedProduct = getPromotedProduct($game);
    if ($promotedProduct) {
        $purchasesData['purchases'][] = [
            'purchaseDate' => time() * 1000,
            'generateDate' => time() * 1000,
            'identifier'   => $promotedProduct->identifier,
            'gamer'        => '00702342-0000-1111-2222-c3e1500cafe2',//gamer uuid
            'uuid'         => '00702342-0000-1111-2222-c3e1500beef3',//transaction ID
            'priceInCents' => $promotedProduct->originalPrice * 100,
            'localPrice'   => $promotedProduct->localPrice,
            'currency'     => $promotedProduct->currency,
        ];
    }

    $encryptedOnce  = dummyEncrypt($purchasesData);
    $encryptedTwice = dummyEncrypt($encryptedOnce);
    return $encryptedTwice;
}

function buildSearch($games)
{
    $games = sortByTitle($games);
    $results = [];
    foreach ($games as $game) {
        $results[] = [
            'title' => $game->title,
            'url'   => 'ouya://launcher/details?app=' . $game->packageName,
            'contentRating' => $game->contentRating,
        ];
    }
    return [
        'count'   => count($results),
        'results' => $results,
    ];
}

function dummyEncrypt($data)
{
    return [
        'key'  => base64_encode('0123456789abcdef'),
        'iv'   => 't3jir1LHpICunvhlM76edQ==',//random bytes
        'blob' => base64_encode(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ),
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

function addDiscoverRow(&$data, $title, $games, $ranked = false)
{
    $row = [
        'title'     => $title,
        'showPrice' => true,
        'ranked'    => $ranked,
        'tiles'     => [],
    ];
    foreach ($games as $game) {
        if (is_string($game)) {
            //category link
            $tilePos = count($data['tiles']);
            $data['tiles'][$tilePos] = buildDiscoverCategoryTile($game);

        } elseif ($game->relationships->launcher ?? false) {
            //gamestick game with a launcher - link bundle
            $tilePos = findBundleTile($data['tiles'], $game->launcherBundleUuid);
            $launcher = $game->relationshipObjects->launcher;
            if ($tilePos === null) {
                $tilePos = count($data['tiles']);
                $data['tiles'][$tilePos] = buildDiscoverBundleTile($game, $launcher);
            }

        } else {
            //game
            if (isset($game->relationships->original)) {
                //do not link unlocked games or launchers.
                // people can access them via the original games
                continue;
            }
            $tilePos = findPackageTile($data['tiles'], $game->packageName);
            if ($tilePos === null) {
                $tilePos = count($data['tiles']);
                $data['tiles'][$tilePos] = buildDiscoverGameTile($game);
            }
        }
        $row['tiles'][] = $tilePos;
    }
    $data['rows'][] = $row;
}

function findPackageTile($tiles, $packageName)
{
    foreach ($tiles as $pos => $tile) {
        if (($tile['package'] ?? null) == $packageName) {
            return $pos;
        }
    }
    return null;
}

function findBundleTile($tiles, $bundleUuid)
{
    foreach ($tiles as $pos => $tile) {
        if (($tile['uuid'] ?? null) == $bundleUuid) {
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
        'promotedProduct' => buildProduct(getPromotedProduct($game)),
    ];
}

function buildDiscoverBundleTile($game, $launcher)
{
    $promotedProduct = getPromotedProduct($game);

    return [
        'title' => $game->title . ' LB',
        'image' => $game->discover,
        'uuid'  => $game->launcherBundleUuid,
        'type'  => 'details_page',
        'url'   => 'ouya://launcher/details?page=' . $game->launcherBundleUuid,

        'bundle' => [
            'apps' => [
                $game->packageName,
                $launcher->packageName,
            ],
            'currency'      => $promotedProduct->currency ?? 'EUR',
            'price'         => $promotedProduct->originalPrice ?? 0,
            'contentRating' => $game->contentRating,
            'purchased'     => true,//???
            'purchaseUrl' => 'ouya://launcher/purchase?developer=' . $launcher->developer->uuid . '&product=' . $game->launcherBundleUuid,
        ],
    ];
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
    global $cfgEnableQr;

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

    $game->firstRelease  = null;
    $game->latestRelease = null;
    $firstReleaseTimestamp  = null;
    $latestReleaseTimestamp = 0;
    foreach ($game->releases as $release) {
        if (isset($release->broken) && $release->broken) {
            continue;
        }
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
        if ($firstReleaseTimestamp === null
            || $releaseTimestamp < $firstReleaseTimestamp
        ) {
            $game->firstRelease    = $release;
            $firstReleaseTimestamp = $releaseTimestamp;
        }
    }
    if ($game->firstRelease === null) {
        error('No first release for ' . $game->packageName);
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

    if ($cfgEnableQr && $game->website) {
        $qrfileName = preg_replace('#[^\\w\\d._-]#', '_', $game->website) . '.png';
        $qrfilePath = $GLOBALS['qrDir'] . $qrfileName;
        if (!file_exists($qrfilePath)) {
            $cmd = __DIR__ . '/create-qr.sh'
                 . ' ' . escapeshellarg($game->website)
                 . ' ' . escapeshellarg($qrfilePath);
            passthru($cmd, $retval);
            if ($retval != 0) {
                exit(20);
            }
        }
        $qrUrlPath = $GLOBALS['baseUrl'] . 'gen-qr/' . $qrfileName;
        $game->media[] = (object) [
            'type' => 'image',
            'url'  => $qrUrlPath,
        ];
    }

    //rewrite urls from Internet Archive to our servers
    $game->discover = rewriteUrl($game->discover);
    foreach ($game->media as $medium) {
        $medium->url = rewriteUrl($medium->url);
    }
    foreach ($game->releases as $release) {
        $release->url = rewriteUrl($release->url);
    }
}

/**
 * Implements a sensible ranking system described in
 * https://stackoverflow.com/a/1411268/2826013
 */
function calculateRank(array $games)
{
    $averageRatings = array_map(
        function ($game) {
            return $game->rating->average;
        },
        $games
    );
    $average = array_sum($averageRatings) / count($averageRatings);
    $C = $average;
    $m = 500;

    foreach ($games as $game) {
        $R = $game->rating->average;
        $v = $game->rating->count;
        $game->rating->rank = ($R * $v + $C * $m) / ($v + $m);
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

/**
 * vimeo only work with HTTPS now,
 * and the OUYA does not support SNI.
 * We get SSL errors and no video for them :/
 */
function isUnsupportedVideoUrl($url)
{
    return strpos($url, '://vimeo.com/') !== false;
}

function removeLauncherGenre(array $genres): array
{
    $filtered = [];
    foreach ($genres as $genre) {
        if ($genre != 'Launcher') {
            $filtered[] = $genre;
        }
    }
    return $filtered;
}

function removeMakeGames(array $games)
{
    return filterByGenre($games, 'Tutorials', true);
}

function removeMakeGenres($genres)
{
    $filtered = [];
    foreach ($genres as $genre) {
        if ($genre != 'Tutorials' && $genre != 'Builds'
            && $genre != 'Launcher'
        ) {
            $filtered[] = $genre;
        }
    }
    return $filtered;
}

function rewriteUrl($url)
{
    foreach ($GLOBALS['urlRewrites'] as $pattern => $replacement) {
        $url = preg_replace($pattern, $replacement, $url);
    }
    return $url;
}

function writeJson($path, $data)
{
    global $cfgMini, $wwwDir;
    $fullPath = $wwwDir . $path;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $opts = JSON_UNESCAPED_SLASHES;
    if (!$cfgMini) {
        $opts |= JSON_PRETTY_PRINT;
    }
    file_put_contents(
        $fullPath,
        json_encode($data, $opts) . "\n"
    );
}

function writeCategoryJson($path, $data)
{
    writeJson($path, $data);

    $forgePath = str_replace('.json', '.forge.json', $path);
    $forgeData = convertCategoryToForge($data, true);
    writeJson($forgePath, $forgeData);
}

function error($msg)
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}
?>
