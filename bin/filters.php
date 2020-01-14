<?php
function filterByAge($origGames, $age)
{
    $filtered = [];
    foreach ($origGames as $game) {
        if ($age == $game->contentRating) {
            $filtered[] = $game;
        }
    }
    return $filtered;
}

function filterByGenre($origGames, $genre, $remove = false)
{
    $filtered = [];
    foreach ($origGames as $game) {
        if ($remove) {
            if (array_search($genre, $game->genres) === false) {
                $filtered[] = $game;
            }
        } else {
            if (array_search($genre, $game->genres) !== false) {
                $filtered[] = $game;
            }
        }
    }
    return $filtered;
}

function filterByLetter($origGames, $letter)
{
    $filtered = [];
    foreach ($origGames as $game) {
        $gameLetter = strtoupper($game->title{0});
        if (!preg_match('#^[A-Z]$#', $gameLetter)) {
            $gameLetter = 'Other';
        }
        if ($letter == $gameLetter) {
            $filtered[] = $game;
        }
    }
    return $filtered;
}

function filterByPackageNames($origGames, $packageNames)
{
    $names = array_flip($packageNames);
    $filtered = [];
    foreach ($origGames as $game) {
        if (isset($names[$game->packageName])) {
            $filtered[] = $game;
        }
    }
    return $filtered;
}

function filterByPlayers($origGames, $numOfPlayers)
{
    $filtered = [];
    foreach ($origGames as $game) {
        if (array_search($numOfPlayers, $game->players) !== false) {
            $filtered[] = $game;
        }
    }
    return $filtered;
}

function filterLastUpdated($origGames, $limit)
{
    $games = array_values($origGames);
    usort(
        $games,
        function ($gameA, $gameB) {
            return strtotime($gameB->latestRelease->date) - strtotime($gameA->latestRelease->date);
        }
    );

    return array_slice($games, 0, $limit);
}

function filterBestRated($origGames, $limit)
{
    $games = array_values($origGames);
    usort(
        $games,
        function ($gameA, $gameB) {
            return $gameB->rating->average - $gameA->rating->average;
        }
    );

    return array_slice($games, 0, $limit);
}
?>
