<?php
/**
 * Functions needed by import-game-data.php and build-html.php
 */

function categoryPath($title)
{
    if ($title == 'Tutorials') {
        //OUYAs fetch "Make" from "discover/tutorials"
        $title = strtolower($title);
    }
    return str_replace(['/', '\\', ' ', '+', '?', '!'], '_', $title);
}
?>
