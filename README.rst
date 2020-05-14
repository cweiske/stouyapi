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


Apache setup
============
Virtual host configuration::

  Script PUT /empty-json.php
  Script DELETE /api/v1/queued_downloads_delete.php

``mod_actions`` need to be enabled for apache 2.4.

The virtual host's document root needs to point to the ``www`` folder.


Building API data
=================
Download the OUYA game data repository from
https://github.com/ouya-saviors/ouya-game-data
and then generate the API files with it::

    $ git clone https://github.com/ouya-saviors/ouya-game-data.git
    $ ./bin/import-game-data.php ouya-game-data/folders


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
