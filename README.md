mbta-bus-speed
==============

Scripts for collecting NextBus data and drawing maps of bus locations and speeds over time.

php/getbuses.php
------------

Scrapes NextBus for bus locations and saves them to a database. Existing data older than 24 hours are deleted from the database.

php/image.php
---------

Generates an image of the last 24 hours (or a specified time period, in seconds, using a "since" parameter). The image is saved twice, once as current.jpg and once as a timestamped image for archiving. (Or alter this to just output the image.)

php/create_tiles.php
-----------

Creates map tiles by drawing images at several scales and cutting them up.

map
---

Simple Leaflet-based map, and mbtiles for a Boston basemap. Tiles served using [PHP-MBTiles-Server](https://github.com/bmcbride/PHP-MBTiles-Server) by Bryan McBride.
