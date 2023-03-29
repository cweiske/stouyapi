**************************
stouyapi - Static OUYA API
**************************

A static API for the OUYA gaming console that still lets you sign in
and install games, despite the OUYA server shutdown in 2019.


=====
Setup
=====

OUYA config change
==================
- Mount via USB (Micro USB cable)
- Create file ``ouya_config.properties``
- Add::

    OUYA_SERVER_URL=http://stouyapi.boo
    OUYA_STATUS_SERVER_URL=http://stouyapi.boo/api/v1/status

The changes should take effect immediately.
If they do not, reboot the OUYA once.


OUYA setup
==========

1. User registration: "Existing account"
2. Enter any username, leave password empty. Continue.
3. Skip credit card registration

The username will appear on your ouya main screen.


Apache setup
============
Virtual host configuration::

  <VirtualHost *:80>
    ServerName stouyapi.test
    DocumentRoot /path/to/stouyapi/www

    CustomLog /var/log/apache2/stouyapi-access.log combined
    ErrorLog  /var/log/apache2/stouyapi-error.log

    Script PUT /empty-json.php
    Script DELETE /api/v1/queued_downloads_delete.php

    <Directory "/path/to/stouyapi/www">
      AllowOverride All
      Require all granted
    </Directory>
  </VirtualHost>

The following modules need to be enabled in Apache 2.4
(with e.g. ``a2enmod``):

- ``actions``
- ``expires``
- ``php`` (or php-fpm via fastcgi)
- ``rewrite``

The virtual host's document root needs to point to the ``www`` folder.


Test your Apache setup
----------------------
::

   # check if normal API routes work
   $ curl -I http://stouyapi.cwboo/api/firmware_builds
   HTTP/1.1 200 OK
   [...]

   # check if rewritten API routes work
   $ curl -I http://stouyapi.cwboo/api/v1/discover/discover
   HTTP/1.1 200 OK
   [...]

   # check if PHP routes work
   curl -I http://stouyapi.cwboo/api/v1/gamers/me
   HTTP/1.1 200 OK
   [...]


Building API data
=================
Download the OUYA game data repository from
https://github.com/ouya-saviors/ouya-game-data
and then generate the API files with it::

    $ git clone https://github.com/ouya-saviors/ouya-game-data.git
    $ ./bin/import-game-data.php ouya-game-data/folders


Building the web discover store
===============================
After building the API files, generate the HTML::

  $ ./bin/build-html.php


===============
Push to my OUYA
===============
stouyapi's HTML game detail page have a "Push to my OUYA" button that
allows anyone to tell his own OUYA to install that game.
It works without any user accounts, and is only based on IP addresses.

If your PC that you click the Push button on and your OUYA have the same
public IP address (IPv4 NAT), or the same IPv6 64bit prefix, then
the OUYA will install the game within 5 minutes.

It will also work if you run stouyapi inside your local network, because
all private IP addresses are mapped to a special "local" address.

You can inspect your own download queue by simply opening
``/api/v1/queued_downloads`` in your browser.


========
See also
========

- https://gitlab.com/devirich/BrewyaOnOuya - alternative storefront
- https://archive.org/details/ouyalibrary - Archived OUYA games
- https://github.com/ouya-saviors/ouya-game-data/ - OUYA game data repository


===========
Discoveries
===========

- data/data/tv.ouya/cache/ion/

  - image cache for main menu image

- Don't put a trailing slash into ``OUYA_SERVER_URL`` - it will make double slashes
