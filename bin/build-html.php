<?php
/**
 * Take the generated JSON files and convert them to HTML for a browser
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 */
require_once __DIR__ . '/functions.php';

$wwwDir = __DIR__ . '/../www/';
$discoverDir = __DIR__ . '/../www/api/v1/discover-data/';
$wwwDiscoverDir = $wwwDir . 'discover/';
$gameDetailsDir = __DIR__ . '/../www/api/v1/details-data/';
$wwwGameDir = $wwwDir . 'game/';

if (!is_dir($wwwDiscoverDir)) {
    mkdir($wwwDiscoverDir, 0755);
}
if (!is_dir($wwwGameDir)) {
    mkdir($wwwGameDir, 0755);
}

foreach (glob($gameDetailsDir . '*.json') as $gameDataFile) {
    $htmlFile = basename($gameDataFile, '.json') . '.htm';
    file_put_contents(
        $wwwGameDir . $htmlFile,
        renderGameFile($gameDataFile)
    );
}

foreach (glob($discoverDir . '*.json') as $discoverFile) {
    $htmlFile = basename($discoverFile, '.json') . '.htm';
    if ($htmlFile == 'discover.htm') {
        $htmlFile = 'index.htm';
    }
    file_put_contents(
        $wwwDiscoverDir . $htmlFile,
        renderDiscoverFile($discoverFile)
    );
}

function renderDiscoverFile($discoverFile)
{
    $json = json_decode(file_get_contents($discoverFile));

    $title    = $json->title;
    $sections = [];
    foreach ($json->rows as $row) {
        $section = (object) [
            'title' => $row->title,
            'tiles' => [],
        ];
        foreach ($row->tiles as $tileId) {
            $tileData = $json->tiles[$tileId];
            if ($tileData->type == 'app') {
                $section->tiles[] = (object) [
                    'type'        => $tileData->type,//app
                    'thumb'       => $tileData->image,
                    'title'       => $tileData->title,
                    'rating'      => $tileData->rating->average,
                    'ratingCount' => $tileData->rating->count,
                    'detailUrl'   => '../game/' . str_replace(
                        'ouya://launcher/details?app=',
                        '',
                        $tileData->url
                    ) . '.htm',
                ];
            } else {
                $section->tiles[] = (object) [
                    'type'        => $tileData->type,//discover
                    'thumb'       => $tileData->image,
                    'title'       => $tileData->title,
                    'detailUrl'   => str_replace(
                        'ouya://launcher/discover/',
                        '',
                        $tileData->url
                    ) . '.htm',
                ];
            }
        }
        $sections[] = $section;
    }

    $navLinks = [];
    if ($title == 'DISCOVER') {
        $navLinks['../'] = 'back';
    } else {
        $navLinks['./'] = 'discover';
    }

    $discoverTemplate = __DIR__ . '/../data/templates/discover.tpl.php';
    ob_start();
    include $discoverTemplate;
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}

function renderGameFile($gameDataFile)
{
    $json = json_decode(file_get_contents($gameDataFile));
    $appsDir = dirname($gameDataFile, 2) . '/apps/';
    $downloadJson = json_decode(
        file_get_contents(
            $appsDir . $json->version->uuid . '-download.json'
        )
    );
    $apkDownloadUrl = $downloadJson->app->downloadLink;

    $navLinks = [];
    foreach ($json->genres as $genreTitle) {
        $url = '../discover/' . categoryPath($genreTitle) . '.htm';
        $navLinks[$url] = $genreTitle;
    }

    $gameTemplate = __DIR__ . '/../data/templates/game.tpl.php';
    ob_start();
    include $gameTemplate;
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}
?>
