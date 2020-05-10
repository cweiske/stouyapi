<?php
/**
 * Take the generated JSON files and convert them to HTML for a browser
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 */
$wwwDir = __DIR__ . '/../www/';
$discoverDir = __DIR__ . '/../www/api/v1/discover-data/';
$wwwDiscoverDir = $wwwDir . 'discover/';

if (!is_dir($wwwDiscoverDir)) {
    mkdir($wwwDiscoverDir, 0755);
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

    $discoverTemplate = __DIR__ . '/../data/templates/discover.tpl.php';
    ob_start();
    include $discoverTemplate;
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}
?>
