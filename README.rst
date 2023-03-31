**************************
stouyapi - Static OUYA API
**************************

A static API for the OUYA gaming console that still lets you sign in
and install games, despite the OUYA server shutdown in 2019.

======================================
Building and enabling a local stouyapi
======================================

The whole procedure was done on Linux, using Pop-OS, release Pop!_OS 22.04 LTS.
The configuration will be the same for any Ubuntu/Debian based distro.

NOTE: Commands with the $ symbol mean they should be run with your default user.
Commands with # means they must be run as root.

1 - Installing the dependencies:
================================

To build the stouyapi API and HTML files on Pop-OS you need to install the 
following packages:

- imagemagick
- exiftool
- qrencode
- ttf-mscorefonts-installer (*)

(*) Pop-OS complained about missing helvetica font, so this package is needed. 
I haven't tested it with another Helvetica compatible font package. This package 
just installs the actual font installer. A screen will appear asking you to 
accept the fonts EULA and continue with the installation.

To run the server, you need to install the following packages:

- apache
- php (*)
- php-sqlite

(*) When installed, it already activates the necessary module in apache.

To install the packages on Pop-OS, just use the following command::

    # apt install imagemagick exiftool qrencode ttf-mscorefonts-installer apache2 php php-sqlite3

**ATTENTION: The above listing is not definitive and may vary if you use another
distro such as Fedora, CentOS, etc. Make sure you have the package installed on
your distribution.**

2 - Building the API and HTML files:
====================================

First download the stouyapi code and files to your computer.

In a terminal, type::

    $ git clone https://github.com/cweiske/stouyapi.git

This will create the stouyapi directory.

Now enter in the stouyapi directory and download the ouya-game-data code and files::

    $ cd stouyapi
    $ git clone https://github.com/ouya-saviors/ouya-game-data.git

**ATTENTION: If you want to add a game/program to your local store, now is the time: 
Just include the json file of the game/program in the "ouya-game-data/new" folder, 
before importing the data.**

Now, before creating the API files and HTML files, you must rename and, if you wish, 
edit the config.php.dist file.

This file:

- Changes all links pointing to the archive.org site to point to static.ouya.world;
- Configures the list of indicated games that appears on the OUYA home screen (where we have the options PLAY, DISCOVER, etc.);
- Configures a list of suggested games, which appears within DISCOVER, below the list of new games.

Rename the config.php.dist file to config.php::

    $ mv config.php.dist config.php

If you want to edit it, open it with nano or any text editor of your choice::

    $ nano config.php

You should only change the following two sections:

The first section is personal game recommendations within DISCOVER, below the new games listing::

    $GLOBALS['packagelists']["cweiske's picks"] = [
             'de.eiswuxe.blookid2',
             'com.cosmos.babylonantwins',
             'com.inverseblue.skyriders',
    ];

If you want:

- Change the title, cweiske's picks, keeping the double quotes,
- Change/include a game by informing the name of the game's json file between single quotes and a comma at the end, following the same formatting as above. If you want to Delete a game, just delete the line.

The session below are indications of games that appear on the OUYA home screen::

    $GLOBALS['home']['2020 Winter GameJam'] = [
        'com.DESKINK.ToneTests',
        'com.Eightbbgames.yahayor',
        'com.FuzzyPopcorn',
        'com.NYYLE.NYCTO',
        'com.NoelRojasOliveras.PaintKiller',
        'com.StrawHat.Fall',
        'com.oliverstogden.trf',
        'com.samuelsousa.shootdestroy',
        'com.scorpion.shootout',
        'com.sd_game_dev.aliens_taste_my_sword',
        'com.sumotown.sirtet',
        'de.x2041.games.gyrogun',
        'ht.sr.git.arcticGrind.embed',
        'tv.ouya.demo.DarkSpacePioneer',
        'tv.ouya.win.unity.brokenbeauty',
    ];

Edit in the same way, but note that on the home screen the title of the recommendations, 
2020 Winter GameJam, is enclosed in single quotes.

Do not change any other field in the file and after making changes, save it.

Now generate the API files::

    $ ./bin/import-game-data.php ouya-game-data/folders

Creating the files takes a while. Wait to finish.

When finished, create the HTML files::

    $ ./bin/build-html.php

3 - Setting up the site
========================

So far, apache is already running. If you type in the browser http://localhost the default 
apache website will appear. Now let's create the settings for the STOUYAPI.

In the terminal, type::

    $ cd /etc/apache2/sites-available/

Now, copy the apache default site file and rename it however you want but keep the ".conf" 
extension. I left it with the name of stouyapi::

    # cp 000-default.conf stouyapi.conf

The file we copied is a file with minimal apache default settings for virtual hosts.

Now let's edit it with nano::

    # nano stouyapi.conf

Now, look for the line that looks like below::

    #ServerName www.example.com

It tells apache the address of the site. Uncomment it (remove the #) and change the address 
to whatever you like. Here I left it like this::

    ServerName stouyapi.local

Now find a line that looks like below::

    DocumentRoot /var/www/html

That line basically tells apache where the site's files are. 
I chose to leave my files in the following path::

    DocumentRoot /srv/stouyapi/www

**ATTENTION: You can use any directory name you want, but 
remember that the path you enter must be complete until the 
folder that contains the files and folders on the server. 
They are all those that are inside the www directory, inside 
the stouyapi folder where we generate the API files and HTML files.**

Now let's go to the end of the file, and before the line below::

    </VirtualHost>

Include the following lines::

    Script PUT /empty-json.php
    Script DELETE /api/v1/queued_downloads_delete.php

    <Directory /srv/stouyapi/www>
        AllowOverride All
        Require all granted
    </Directory>

**ATTENTION: Pay attention that the path in "DocumentRoot" and "<Directory>" should be the same.**

In the end, disregarding all the comment lines that the file has, it will look like this::

	<VirtualHost *:80>

		ServerName stouyapi.local
        
		ServerAdmin webmaster@localhost
		DocumentRoot /srv/stouyapi/www

	        ErrorLog ${APACHE_LOG_DIR}/error.log
	        CustomLog ${APACHE_LOG_DIR}/access.log combined

		Script PUT /empty-json.php
		Script DELETE /api/v1/queued_downloads_delete.php

		<Directory /srv/stouyapi/www>
			AllowOverride All
			Require all granted
		</Directory>

	</VirtualHost>

Save the file and close.

Now let's move the site files to the location indicated in the configuration file.

Do::

    # mkdir /srv/stouyapi

Then go inside the stouyapi folder where we created the API and HTML files and do::

    # cp -R www /srv/stouyapi

This will copy the www folder to /srv/stouyapi.

You can check with the following command::

    $ ls /srv/stouyapi

Which will return the www folder.

4 - Activating the apache modules and the website.
==================================================

With the configuration file created and the site files in place, let's activate the modules and the site.

First the modules, enter the following command::

    # a2enmod actions expires php8.1 rewrite

This will activate the necessary modules. Don't worry if any of them are already active 
(php8.1 will be), as apache just tells you that it's already configured.

It will ask to restart apache, showing the command to run which is::

    # systemctl restart apache2

Finally, to activate the site, type::

    # a2ensite stouyapi
    
If you used another name for the site configuration file, change the name in the above command. 
If you just type a2ensite and press enter it will show you all the sites available to activate 
and you just type the name of the site and press enter.

Finally, it will ask to reload apache, which we will do with the command::

    # systemctl reload apache2

With that we finish the settings and the site is already running.

To check if everything is ok, in the terminal::

    ##To check if normal API routes work, type:
    $ curl -I http://stouyapi.cwboo/api/firmware_builds

    ##To check if rewritten API routes work, type:
    $ curl -I http://stouyapi.cwboo/api/v1/discover/discover

    ##To check if PHP routes work, type:
    $ curl -I http://stouyapi.cwboo/api/v1/gamers/me

All curl commands above should return ``HTTP/1.1 200 OK`` with some other information.

5 - Configuring the files in the OUYA
=====================================

We must access the OUYA through adb, either in the case of an installation after a factory reset 
or to use the local stouyapi, and edit the hosts file located in /etc (/etc/hosts) and include a 
line with the format below::

    IP-APACHE-SERVER STOUYAPI-SITE-NAME

It will look like this::

    127.0.0.1 localhost
    192.168.0.5 stouyapi.local

ATTENTION: The hosts file already has a line that refers to localhost and it should not be deleted. 
Also, you must leave a blank line after your stouyapi address.

And the ouya_config.properties file, which is in /sdcard, will look like this::

    OUYA_SERVER_URL=http://stouyapi.local
    OUYA_STATUS_SERVER_URL=http://stouyapi.local/api/v1/status

ATTENTION: the site to be used, which in the above case is stouyapi.local, is the one that we inform 
in the apache configuration file, in the line that starts with "ServerName".

With this, the OUYA will use the local stouyapi immediately.
If it do not, reboot the OUYA once.

6 - OUYA setup
==============

1. User registration: "Existing account"
2. Enter any username, leave password empty. Continue.
3. Skip credit card registration

The username will appear on your ouya main screen.

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
