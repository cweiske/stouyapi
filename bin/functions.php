<?php
/**
 * Functions needed by import-game-data.php and build-html.php
 */

function categoryPath($title)
{
    return str_replace(['/', '\\', ' ', '+', '?', '!'], '_', $title);
}
?>
