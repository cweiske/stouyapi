#!/bin/sh
# remove all autogenerated files
rm -f www/api/v1/apps/*
rm -f www/api/v1/details-data/*
rm -f -r www/api/v1/developers/*
rm -f www/api/v1/discover-data/*
rm -f www/api/v1/games/*/*
rmdir  www/api/v1/games/*/
rm -f www/api/v1/search-data/*
rm -f www/discover/*
rm -f www/game/*
