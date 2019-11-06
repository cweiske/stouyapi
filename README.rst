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
    OUYA_STATUS_SERVER_URL=http://stouyapi.boo

Notes:

- "important note, don't put trailing slash into OUYA_SERVER_URL, it will make double slashes"
- I had to reboot to make that change in the file active


OUYA setup
==========

1. User registration: "Existing account"
2. Enter any username, leave password empty. Continue.
3. Skip credit card registration



===========
Information
===========
By default, OUYA uses HTTPS to devs.ouya.tv.
(status.ouya.tv is HTTP only, no SSL)
DNS mapping does not work, except when creating an own SSL certificate
and registering the root CA at the OUYA itself.

IPv6 used -> custom domain needs IPv6 DNS entry

https://rabid.ouya.tv/ - was OUYA's sandbox instance

DEBUG=1
DEBUG_SPAM=1

========
See also
========

- https://gitlab.com/devirich/BrewyaOnOuya
- https://archive.org/details/ouyalibrary
- https://github.com/cweiske/ouya-game-data/
